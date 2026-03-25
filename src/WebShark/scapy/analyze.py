import sys
import os
import psycopg2
import psycopg2.extras
import json
import pyshark

# So, in our case we have layers: L3, L4 and L7. Each layer has its own registry.
# The registry is a dict that maps the protocol name to the function that can handle it.
# A dict is a data structure that maps keys to values (like hashmap in other languages).
# in our case dict that maps: layer -> protocol name -> function.

# L7 fields and their explanations, fields are kept with the same name as in DB
# l7_version
# l7_status_codes - for example http 404, 403...
# l7_reason_phrase - general translation of status codes
# l7_method - for example http get, put...
# l7_path - the path that this packet is trying to access (usually web protocols)
# l7_payload - retrievable data in a packet (http contents)
# l7_attributes - additional non primary data like cookies and more

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
    if "IP" not in pkt:
        return None
    ip = pkt["IP"]
    return {
        "protocol": "IPv4",
        "src": ip.src,
        "dst": ip.dst,
        "ttl": ip.ttl,
        "length": ip.len,
        "protocol_num": ip.proto,
    }

def handle_ipv6(pkt):
    if "IPv6" not in pkt:
        return None
    ip6 = pkt["IPv6"]
    return {
        "protocol": "IPv6",
        "src": ip6.src,
        "dst": ip6.dst,
        "hop_limit": ip6.hlim,
        "payload_len": ip6.plen,
    }

register("L3", "IPv4", handle_ipv4)
register("L3", "IPv6", handle_ipv6)

# L4 handlers
def handle_tcp(pkt):
    if "TCP" not in pkt:
        return None
    tcp = pkt["TCP"]
    return {
        "protocol": "TCP",
        "src_port": tcp.srcport,
        "dst_port": tcp.dstport,
        "seq": tcp.seq,
        "ack": tcp.ack,
        "flags": str(tcp.flags),
        "window": tcp.window_size,
    }

def handle_udp(pkt):
    if "UDP" not in pkt:
        return None
    udp = pkt["UDP"]
    return {
        "protocol": "UDP",
        "src_port": udp.srcport,
        "dst_port": udp.dstport,
        "length": udp.length,
    }

def handle_icmp(pkt):
    if "ICMP" not in pkt:
        return None
    icmp = pkt["ICMP"]
    return {
        "protocol": "ICMP",
        "type": icmp.type,
        "code": icmp.code,
    }

register("L4", "TCP", handle_tcp)
register("L4", "UDP", handle_udp)
register("L4", "ICMP", handle_icmp)

# L7 handlers
def handle_dns(pkt):
    if "DNS" not in pkt:
        return None
    dns = pkt["DNS"]

    return {
        "protocol": "DNS",
        "id": dns.id,
        #"qr": dns.qr, # 0 = question, 1 = answer?
        #"qd_count": dns.count.queries,
        #"an_count": dns.count.answers,
        #"query": query, ?
    }

def handle_http(pkt):
    if "HTTP" not in pkt:
        return None
    http = pkt["HTTP"]
    http_header = {
        "protocol": "HTTP",
        "version": None,
    }
    if http.get("request"):
        http_header.update({"version": http.get("request_version")})
        http_header["request_method"] = http.get("request_method")
        http_header["uri_path"] = http.get("request_uri")
    elif http.get("response"):
        http_header.update({"version": http.get("response_version")})
        http_header["status_code"] = http.get("response_code")
        http_header["response_phrase"] = http.get("response_phrase")
        http_header["cookie"] = http.get("set_cookie")
    return http_header
        

register("L7", "DNS", handle_dns)
register("L7", "HTTP", handle_http)

# Hex dump fallback for unknown protocols
# This needs an alternative as PyShark does not return raw bytes (maybe using scapy only for this)
def get_hex_dump(pkt):
    try:
        raw_bytes = raw(pkt) # f"{byte:02x}" formats each byte as a 2-digit hex number. 255 -> ff, 0 -> 00, 16 -> 10
        return " ".join(f"{byte:02x}" for byte in raw_bytes)
    except Exception:
        return None

# Identify which protocol a packet uses by looking at its layers
def identify_l3(pkt):
    if "IP" in pkt:
        return "IPv4"
    if "IPv6" in pkt:
        return "IPv6"
    return None

def identify_l4(pkt):
    if "TCP" in pkt:
        return "TCP"
    if "UDP" in pkt:
        return "UDP"
    if "ICMP" in pkt:
        return "ICMP"
    return None

def identify_l7(pkt):
    if "DNS" in pkt:
        return "DNS"
    if "HTTP" in (pkt):
        return "HTTP"

    # ToDo: Add more L7 protocols (HTTP, TLS, ...)
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
        "layers": {
            "L3": {},
            "L4": {},
            "L7": {},
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

    # L7
    l7_name = identify_l7(pkt)
    if l7_name and l7_name in handlers["L7"]:
        result["layers"]["L7"] = handlers["L7"][l7_name](pkt)

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


with pyshark.FileCapture(file_path, keep_packets = False) as packet:
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
            l7_protocol,
            l7_attributes
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""
    rows = []
    for index, pkt in enumerate(packet):
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
            result["layers"]["L7"].get("protocol"),
            json.dumps(result["layers"]["L7"])
        ))

        # Execute batch works faster than inserting line by line (about 20-30%), depending on the need, faster alternative might be required
        # Executing all rows tends to reduce 1-2 seconds (for 2mb file), but increases memory consumption
        # Comparing execute_batch with executemany (~2mb file) revealed no difference despite documentation telling different
        if len(rows) >= row_limit:
            psycopg2.extras.execute_batch(cursor, query, rows)
            conn.commit()
            rows.clear()

packet.close()
if len(rows) > 0:
    psycopg2.extras.execute_batch(cursor, query, rows)
    conn.commit()

# Close DB connection
cursor.close()
conn.close()