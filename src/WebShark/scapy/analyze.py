import sys
import os
import psycopg2
import psycopg2.extras
import json
from scapy.all import PcapReader, raw, IP, IPv6, TCP, UDP, ICMP
import subprocess

# So, in our case we have layers: L3, L4 and L7. Each layer has its own registry.
# The registry is a dict that maps the protocol name to the function that can handle it.
# A dict is a data structure that maps keys to values (like hashmap in other languages).
# in our case dict that maps: layer -> protocol name -> function.

# Define upper protocol fields for retreival. All of them can be found here  https://www.wireshark.org/docs/dfref/
l7_protocols = {
    # HTTP 1/1.1 fields
    "http1_fields": [ "http.request.version", "http.authorization", "http.response.version", "http.request.method", "http.request.uri", "http.request.full_uri", "http.response.code", "http.response.phrase", "http.user_agent", "http.connection", "http.response.phrase"],
    # DNS fields
    "dns_fields": [ "dns.id", "dns.flags", "dns.flags.response", "dns.qry.name", "dns.qry.type", "dns.resp.name", "dns.resp.type" ],
    # DHCPv4 fields
    "dhcp_fields": [ "dhcp.id", "dhcp.ip.client", "dhcp.ip.relay", "dhcp.ip.server", "dhcp.ip.your",  "dhcp.option.dhcp", "dhcp.option.subnet_mask", "dhcp.option.request_list_item"]
}
# Used to separate fields inside the return of tshark command
# Unique symbol which will never be encountered in real packet data, although crafted packets with this symbol will cause desync (json parsing is better but slower)
field_separator = "\u001f"   # Special ascii character Unit Separator


handlers = {
    "L3": {},
    "L4": {},
    "L7": {},
}

# Add a handler function to the registry
def register(layer, name, func):
    handlers[layer][name] = func

# L3 handlers
def handle_ipv4(pkt):
    if IP not in pkt:
        return None
    ip = pkt[IP]
    return {
        "protocol": "IPv4",
        "src": ip.src,
        "dst": ip.dst,
        "ttl": ip.ttl,
        "length": ip.len,
        "protocol_num": ip.proto,
    }

def handle_ipv6(pkt):
    if IPv6 not in pkt:
        return None
    ip6 = pkt[IPv6]
    return {
        "protocol": "IPv6",
        "src": ip6.src,
        "dst": ip6.dst,
        "hop_limit": ip6.hlim,
        "payload_len": ip6.plen,
        "next_header": ip6.nh,
    }

register("L3", "IPv4", handle_ipv4)
register("L3", "IPv6", handle_ipv6)

# L4 handlers
def handle_tcp(pkt):
    if TCP not in pkt:
        return None
    tcp = pkt[TCP]
    return {
        "protocol": "TCP",
        "src_port": tcp.sport,
        "dst_port": tcp.dport,
        "seq": tcp.seq,
        "ack": tcp.ack,
        "flags": str(tcp.flags),
        "window": tcp.window,
    }

def handle_udp(pkt):
    if UDP not in pkt:
        return None
    udp = pkt[UDP]
    return {
        "protocol": "UDP",
        "src_port": udp.sport,
        "dst_port": udp.dport,
        "length": udp.len,
    }

def handle_icmp(pkt):
    if ICMP not in pkt:
        return None
    icmp = pkt[ICMP]
    return {
        "protocol": "ICMP",
        "type": icmp.type,
        "code": icmp.code,
    }

register("L4", "TCP", handle_tcp)
register("L4", "UDP", handle_udp)
register("L4", "ICMP", handle_icmp)

# Handle L7

def handle_http1(packet):
    http_header = {
        "protocol": "HTTP",
        "version": None
    }
    if packet.get("http.request.version"):
        http_header.update({"version": packet.get("http.request.version")})
        http_header["request_method"] = packet.get("http.request.method")
        http_header["request_uri"] = packet.get("http.request.uri")
        http_header["full_uri"] = packet.get("http.request.full_uri")
        http_header["user_agent"] = packet.get("http.user_agent")
        http_header["user_credentials"] = packet.get("http.authorization")
    elif packet.get("http.response.version"):
        http_header.update({"version": packet.get("http.response.version")})
        http_header["response_code"] = packet.get("http.response.code")
        http_header["response_phrase"] = packet.get("http.response.phrase")
    else:
        return {}
    http_header["keep_alive"] = packet.get("http.connection")
    return http_header

def handle_dns(packet):
    dns_header = {
        "protocol": "DNS",
        "transaction_id": packet.get("dns.id"),
        "flags": packet.get("dns.flags")
    }
    if packet.get("dns.flags.response") == "False":
        dns_header["type"] = "query"
        dns_header["query_name"] = packet.get("dns.qry.name")
        dns_header["query_type"] = packet.get("dns.qry.type")
    elif packet.get("dns.flags.response") == "True":
        dns_header["type"] = "response"
        dns_header["response_name"] = packet.get("dns.resp.name")
        dns_header["response_type"] = packet.get("dns.resp.type")
    else:
        return {}
    
    return dns_header

def handle_dhcp(packet):
    dhcp_header = {
        "protocol": "DHCP",
        "transaction_id": packet.get("dhcp.id"),
        "client_ip_address": packet.get("dhcp.ip.client"),
        "relay_ip_address": packet.get("dhcp.ip.relay"),
        "server_ip_address": packet.get("dhcp.ip.server"),
        "your_ip_address": packet.get("dhcp.ip.relay"),
        "dhcp_message_type": packet.get("dhcp.option.dhcp"),
    }
    # There are many more (https://en.wikipedia.org/wiki/Dynamic_Host_Configuration_Protocol#DHCP_message_types), these are the common ones
    if dhcp_header["dhcp_message_type"] == "1":
        dhcp_header.update({"dhcp_message_type": "1 (DHCPDISCOVER)"})
        dhcp_header["request_list"] = packet.get("dhcp.option.request_list_item")
    elif dhcp_header["dhcp_message_type"] == "2":
        dhcp_header.update({"dhcp_message_type": "2 (DHCPOFFER)"})
        dhcp_header["subnet_mask"] = packet.get("dhcp.option.subnet_mask")
    elif dhcp_header["dhcp_message_type"] == "3":
        dhcp_header.update({"dhcp_message_type": "3 (DHCPREQUEST)"})
        dhcp_header["request_list"] = packet.get("dhcp.option.request_list_item")
    elif dhcp_header["dhcp_message_type"] == "5":
        dhcp_header.update({"dhcp_message_type": "5 (DHCPACK)"})

    return dhcp_header


register("L7", "HTTP1", handle_http1)
register("L7", "DNS", handle_dns)
register("L7", "DHCP", handle_dhcp)


def identify_l7(packet):
    if packet.get("http.request.version") or packet.get("http.response.version"):
        return "HTTP1"
    elif packet.get("dns.id"):
        return "DNS"
    elif packet.get("dhcp.id"):
        return "DHCP"
    return None
    
def analyze_tshark(packet):
    result = {
        "layers":{
            "L7": {},
        }
    }

    l7_name = identify_l7(packet)
    if l7_name in handlers["L7"]:
        result["layers"]["L7"] = handlers["L7"][l7_name](packet)
    
    return result
    

# Create a subprocess (same as fork) that runs tshark
# Subprocess uses a pipe to receive data from tshark (only a limited amount of data is stored in the memory).
def execute_tshark(file_path):
    # Construct required field string
    l7_fields = []
    if l7_protocols is not None:
        for l7_protocol in l7_protocols:
            l7_protocol_fields = l7_protocols.get(l7_protocol)
            if l7_protocol_fields is not None:
                    for l7_field in l7_protocol_fields:
                        l7_fields.extend(["-e", l7_field])
    # If tshark becomes a bottleneck it could be optimized to only parse through the needed protocols
    # In that case synchronization would break. Potential solution would be to insert upper layer data after scapy processing 
    tshark_process = subprocess.Popen(["tshark", "-r", file_path,
        "-T", "fields",
        "-e", "frame.number",
        *l7_fields,
        "-E", f"separator={field_separator}",
        "-E", "header=y"    # Return headers
    ], stdout=subprocess.PIPE, stderr=subprocess.PIPE, text=True)

    # First line returns a header with extracted field names
    header = tshark_process.stdout.readline().replace("\n", "").split(field_separator)

    # Parse other packets, one by one
    for packet in tshark_process.stdout:
        # Join columns with header values into a dict
        packet = packet.replace("\n", "").split(field_separator)
        packet = dict(zip(header, packet))

        yield analyze_tshark(packet)

    # Wait for the process to finish
    tshark_process.wait()


# Hex dump fallback for unknown protocols
def get_hex_dump(pkt):
    try:
        raw_bytes = raw(pkt) # f"{byte:02x}" formats each byte as a 2-digit hex number. 255 -> ff, 0 -> 00, 16 -> 10
        return " ".join(f"{byte:02x}" for byte in raw_bytes)
    except Exception:
        return None

# Identify which protocol a packet uses by looking at its layers
def identify_l3(pkt):
    if IP in pkt:
        return "IPv4"
    if IPv6 in pkt:
        return "IPv6"
    return None

def identify_l4(pkt):
    if TCP in pkt:
        return "TCP"
    if UDP in pkt:
        return "UDP"
    if ICMP in pkt:
        return "ICMP"
    return None

# Validate PCAP
PCAP_MAGIC = [
    b"\xd4\xc3\xb2\xa1",  # pcap little-endian
    b"\xa1\xb2\xc3\xd4",  # pcap big-endian
    b"\x0a\x0d\x0d\x0a",  # pcapng
]

def validate_pcap(file_path):
    try:
        with open(file_path, "rb") as f: # "rb" = read binary mode
            magic = f.read(4)
        return magic in PCAP_MAGIC

    except Exception:
        return False

# Main function
def analyze_packet(pkt, index):
    result = {
        "id": index,
        "length": len(pkt),
        "timestamp": float(pkt.time),
        "layers": {
            "L3": {},
            "L4": {},
        },
        "hex_dump": get_hex_dump(pkt),
    }

    # L3
    l3_name = identify_l3(pkt)
    if l3_name and l3_name in handlers["L3"]:
        result["layers"]["L3"] = handlers["L3"][l3_name](pkt)

    # L4
    l4_name = identify_l4(pkt)
    if l4_name and l4_name in handlers["L4"]:
        result["layers"]["L4"] = handlers["L4"][l4_name](pkt)

    return result

# Entry point
file_path = sys.argv[1]
redis_id = sys.argv[2]
# Get environmental DB connection variables
dbName = os.getenv('DB_DATABASE')
dbUser = os.getenv('DB_USERNAME')
dbPass = os.getenv('DB_PASSWORD')
dbHost = os.getenv('DB_HOST')

if not validate_pcap(file_path):
    print(
        "ERROR:"
        + json.dumps({"error": "Not a valid PCAP file"})
    )
    sys.exit(1)

try:
    # Establish connection to the DB
    conn = psycopg2.connect(f'host={dbHost} dbname={dbName} user={dbUser} password={dbPass}')
    cursor = conn.cursor()
except:
    print(json.dumps({"error": "Failed to establish DB connection"}))
    sys.exit(1)


with PcapReader(file_path) as reader:
    row_limit = 1000
    query = """INSERT INTO packet 
        (
            redis_id, 
            l3_protocol,
            src_ip, 
            dst_ip, 
            captured_packet_length,
            src_port, 
            dst_port,
            l4_protocol,
            l7_attributes
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s)"""
    tshark_stream = execute_tshark(file_path)
    rows = []
    for index, (pkt, tshark_pkt) in enumerate(zip(reader, tshark_stream), start=1):
        result = analyze_packet(pkt, index)
        # Make a list of 1000 tuples and then commit to DB
        rows.append((
            redis_id, 
            result["layers"]["L3"].get("protocol"),
            result["layers"]["L3"].get("src"), 
            result["layers"]["L3"].get("dst"), 
            result["layers"]["L3"].get("length"),
            result["layers"]["L4"].get("src_port"), 
            result["layers"]["L4"].get("dst_port"),
            result["layers"]["L4"].get("protocol"),
            json.dumps(tshark_pkt)
        ))

        # Execute batch works faster than inserting line by line (about 20-30%), depending on the need, faster alternative might be required
        # Executing all rows tends to reduce 1-2 seconds (for 2mb file), but increases memory consumption
        # Comparing execute_batch with executemany (~2mb file) revealed no difference despite documentation telling different
        if len(rows) >= row_limit:
            psycopg2.extras.execute_batch(cursor, query, rows)
            conn.commit()
            rows.clear()

if len(rows) > 0:
    psycopg2.extras.execute_batch(cursor, query, rows)
    conn.commit()

# Close DB connection
cursor.close()
conn.close()