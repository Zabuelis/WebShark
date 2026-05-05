import sys
import os
import psycopg2
import psycopg2.extras
import json
from time import sleep
from scapy.all import PcapReader, raw, IP, IPv6, TCP, UDP, ICMP
import subprocess
from analyzer_modules import *

# So, in our case we have layers: L3, L4 and L7. Each layer has its own registry.
# The registry is a dict that maps the protocol name to the function that can handle it.
# A dict is a data structure that maps keys to values (like hashmap in other languages).
# in our case dict that maps: layer -> protocol name -> function.

# Define upper protocol fields for retreival. All of them can be found here https://www.wireshark.org/docs/dfref/
# Keys are used as filters for tshark, they must exactly match with the documentation
tshark_protocols = {
    # HTTP 1/1.1 fields
    "http": [ "http.request.version", "http.authorization", "http.response.version", "http.request.method", "http.request.uri", "http.request.full_uri", "http.response.code", "http.response.phrase", "http.user_agent", "http.connection", "http.response.phrase", "http.file_data", "http.content_length"],
    # DNS fields
    "dns": [ "dns.id", "dns.flags", "dns.flags.response", "dns.qry.name", "dns.qry.type", "dns.resp.name", "dns.resp.type" ],
    # DHCPv4 fields
    "dhcp": [ "dhcp.id", "dhcp.ip.client", "dhcp.ip.relay", "dhcp.ip.server", "dhcp.ip.your",  "dhcp.option.dhcp", "dhcp.option.subnet_mask", "dhcp.option.request_list_item"],
    # TLS fields
    "tls": [ "tls.app_data_proto", "tls.app_data", "tls.record.version", "tls.record.length" ],
    # SSH fields
    "ssh" : [ "ssh.protocol", "ssh.direction", "ssh.encrypted_packet", "ssh.packet_length", "ssh.packet_length_encrypted", "ssh.message_code" ]
}

# Used to separate fields inside the return of tshark command
# Unique symbol which will never be encountered in real traffic data.
field_separator = "\u001f"   # Special ascii character Unit Separator

# Flow cache dict, stores (src_ip, dst_ip, src_port, dst_port) as a key
# Stores id, initiator_key, receiver_key, initiator_fin, receiver_fin as value
flow_cache = {}

# Tracks new flows, each time upon new flow detection is incremented
flow_num = 0

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

# Handle L7 protocols
def handle_http1(packet):
    http_header = {
        "Protocol": "HTTP",
        "Version": None,
        "Content_Length": packet.get("http.content_length"),
        "Payload": packet.get("http.file_data")
    }
    if packet.get("http.request.version"):
        http_header.update({"Version": packet.get("http.request.version")})
        http_header["Request_Method"] = packet.get("http.request.method")
        http_header["Request_URI"] = packet.get("http.request.uri")
        http_header["Full_URI"] = packet.get("http.request.full_uri")
        http_header["User_Agent"] = packet.get("http.user_agent")
        http_header["User_Credentials"] = packet.get("http.authorization")
    elif packet.get("http.response.version"):
        http_header.update({"Version": packet.get("http.response.version")})
        http_header["Response_Code"] = packet.get("http.response.code")
        http_header["Response_Phrase"] = packet.get("http.response.phrase")
    else:
        return {}
    http_header["Keep_Alive"] = packet.get("http.connection")
    return http_header

def handle_dns(packet):
    dns_header = {
        "Protocol": "DNS",
        "Transaction_ID": packet.get("dns.id"),
        "Flags": packet.get("dns.flags")
    }
    if packet.get("dns.flags.response") == "False":
        dns_header["Type"] = "Query"
        dns_header["Query_Name"] = packet.get("dns.qry.name")
        dns_header["Query_Type"] = packet.get("dns.qry.type")
    elif packet.get("dns.flags.response") == "True":
        dns_header["Type"] = "Response"
        dns_header["Response_Name"] = packet.get("dns.resp.name")
        dns_header["Response_Type"] = packet.get("dns.resp.type")
    else:
        return {}
    
    return dns_header

def handle_dhcp(packet):
    dhcp_header = {
        "Protocol": "DHCP",
        "Transaction_ID": packet.get("dhcp.id"),
        "Client_IP_Address": packet.get("dhcp.ip.client"),
        "Relay_IP_Address": packet.get("dhcp.ip.relay"),
        "Server_IP_Address": packet.get("dhcp.ip.server"),
        "Your_IP_Address": packet.get("dhcp.ip.your"),
        "DHCP_Message_Type": protocol_contexts.dhcp_message_type.get(packet.get("dhcp.option.dhcp")),
        "Subnet_Mask": packet.get("dhcp.option.subnet_mask"),
        "Request_List": packet.get("dhcp.option.request_list_item")
    }
    # Convert request list from code to name
    if dhcp_header["Request_List"]:
        dhcp_header["Request_List"] = helpers.translate_message(",", protocol_contexts.dhcp_request_list, dhcp_header["Request_List"])

    return dhcp_header

def handle_tls(packet):
    return {
        "Protocol": "TLS",
        "Version": protocol_contexts.tls_name_versions.get(packet.get("tls.record.version")),
        "Record_Length": packet.get("tls.record.length"),
        "Encrypted_Protocol": packet.get("tls.app_data_proto"),
        "Encrypted_Content": packet.get("tls.app_data")
    }

def handle_ssh(packet):
    ssh_header = {
        "Protocol": "SSH",
        "SSH_Version": packet.get("ssh.protocol"),
        "SSH_Direction": protocol_contexts.ssh_direction.get(packet.get("ssh.direction")),
        "SSH_Encrypted_Packet": packet.get("ssh.encrypted_packet"),
        "SSH_Packet_Length": packet.get("ssh.packet_length"),
        "SSH_Packet_Length (Encrypted)": packet.get("ssh.packet_length_encrypted"),
        "SSH_Message_Code": packet.get("ssh.message_code"),
    }
    if ssh_header["SSH_Message_Code"]:
        ssh_header.update({"Translated_Message": helpers.translate_message(",", protocol_contexts.ssh_message_codes, ssh_header["SSH_Message_Code"])})

    return ssh_header

register("L7", "TLS", handle_tls)
register("L7", "HTTP1", handle_http1)
register("L7", "DNS", handle_dns)
register("L7", "DHCP", handle_dhcp)
register("L7", "SSH", handle_ssh)

def identify_l7(packet):
    if packet.get("http.request.version") or packet.get("http.response.version"):
        return "HTTP1"
    elif packet.get("dns.id"):
        return "DNS"
    elif packet.get("dhcp.id"):
        return "DHCP"
    elif packet.get("tls.record.version"):
        return "TLS"
    elif packet.get("ssh.direction"):
        return "SSH"
    return None
    
def analyze_tshark(packet):
    result = {
        "id": packet.get("frame.number"),
        "layers":{
            "L7": {},
        }
    }

    l7_name = identify_l7(packet)
    if l7_name and l7_name in handlers["L7"]:
        result["layers"]["L7"] = handlers["L7"][l7_name](packet)
    
    return result
    

# Create a subprocess (same as fork) that runs tshark
# Subprocess uses a pipe to receive data from tshark (only a limited amount of data is stored in the memory).
def create_tshark(file_path):
    # Construct argument string of required fields
    analysis_fields = []
    filter_fields = ""
    if tshark_protocols:
        for protocol in tshark_protocols:
            if protocol:
                filter_fields += " | " + protocol
                protocol_fields = tshark_protocols.get(protocol)
                if protocol_fields:
                        for field in protocol_fields:
                            analysis_fields.extend(["-e", field])

    filter_fields = filter_fields.strip(" | ")
    filter_fields = filter_fields.replace("|", "or")

    try:
        tshark_process = subprocess.Popen(["tshark", "-r", file_path,
            "-Y", filter_fields,    # Only return defined protocols
            "-T", "fields", # Field format
            "-e", "frame.number",   # Return specified fields only
            *analysis_fields,  
            "-E", f"separator={field_separator}",   # Separate fields with a specified separator
            "-E", "header=y"    # Return headers
            # Use the same pipe for stoud and stderr, avoids blocking read in a simplified manner
        ], stdout=subprocess.PIPE, stderr=subprocess.STDOUT, text=True)
    except:
        raise Exception("Tshark failed to start")
    
    return tshark_process

def create_tshark_stream(tshark_process):
    header = tshark_process.stdout.readline()
    # First line returns a header with extracted field names, if it contains "tshark:" it means tshark has encountered an error    
    if "tshark:" in header:
        error_message = tshark_process.stdout.readline()
        raise Exception("Tshark has encountered some errors: " + header + error_message)
    else:
        header = header.replace("\n", "").split(field_separator)

    # Parse other packets, one by one
    for packet in tshark_process.stdout:
        # Join columns with header values into a dict
        packet = packet.replace("\n", "").split(field_separator)
        packet = dict(zip(header, packet))

        yield analyze_tshark(packet)

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
        "flow": None,
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

# Asigns TCP packets to a specific flow, based on flow_cache dict
# Creates new TCP flows upon syn flag detection
# Finishes TCP flows upon fin flag detection
# If scapy will be retired, tshark returns different flag names, they should be changed here.
def reassemble_flows(pkt):
    flags = pkt["layers"]["L4"].get("flags")
    global flow_num

    # Construct a key from IPs and PORTs, prioritize lower port as message source
    # Lower port has no real impact, only normalizes the key creation.
    if pkt["layers"]["L4"].get("src_port") < pkt["layers"]["L4"].get("dst_port"):
        src_ip = pkt["layers"]["L3"].get("src")
        dst_ip = pkt["layers"]["L3"].get("dst")
        src_port = pkt["layers"]["L4"].get("src_port")
        dst_port = pkt["layers"]["L4"].get("dst_port")
    else:
        dst_ip = pkt["layers"]["L3"].get("src")
        src_ip = pkt["layers"]["L3"].get("dst")
        dst_port = pkt["layers"]["L4"].get("src_port")
        src_port = pkt["layers"]["L4"].get("dst_port")

    key = (src_ip, dst_ip, src_port, dst_port)
    sender = (pkt["layers"]["L3"].get("src"), pkt["layers"]["L4"].get("src_port"))

    # S (SYN) flag indicates the beggining of a TCP session
    if "S" in flags and "A" not in flags:
        flow_cache[key] = {
            "id": flow_num,
            "initiator": sender,
            "receiver": (pkt["layers"]["L3"].get("dst"), pkt["layers"]["L4"].get("dst_port")),
            "initiator_fin": False,
            "receiver_fin": False,
        }
        pkt["flow"] = flow_num
        flow_num += 1
    elif flow_cache.get(key) is not None:
        flow = flow_cache[key]
        # F (FIN) flag indicates that one side want to close the connection
        if "F" in flags:
            if sender == flow["receiver"] and flow["initiator_fin"] is True:
                pkt["flow"] = flow["id"]
                flow_cache.pop(key)
            elif sender == flow["initiator"] and flow["receiver_fin"] is True:
                pkt["flow"] = flow["id"]
                flow_cache.pop(key)
            else:
                if sender == flow["initiator"]:
                    flow_cache[key]["initiator"] = True
                elif sender == flow["receiver"]:
                    flow_cache[key]["receiver"] = True
        # R (RST) flag indicates to break the connection right at this moment
        if "R" in flags:
            pkt["flow"] = flow["id"]
            flow_cache.pop(key)
        # Everything else is flow traffic
        else:
            pkt["flow"] = flow["id"]
    # Packets that are not SYN and have no record in the flow cache
    # are interpreted as having no beginning recorded. In this case they also get a unique flow.
    else:
        flow_cache[key] = {
            "id": flow_num,
            "initiator": sender,
            "receiver": (pkt["layers"]["L3"].get("dst"), pkt["layers"]["L4"].get("dst_port")),
            "initiator_fin": False,
            "receiver_fin": False,
        }
        pkt["flow"] = flow_num
        flow_num += 1
    return pkt

# Entry point
file_path = sys.argv[1]
analysis_id = sys.argv[2]

# Get environmental DB connection variables
dbName = os.getenv('DB_DATABASE')
dbUser = os.getenv('DB_USERNAME')
dbPass = os.getenv('DB_PASSWORD')
dbHost = os.getenv('DB_HOST')

if not validate_pcap(file_path):
    print(json.dumps({"error": "Analysis failed due to an invalid PCAP file"}))
    sys.exit(1)

try:
    # Establish connection to the DB
    conn = psycopg2.connect(f'host={dbHost} dbname={dbName} user={dbUser} password={dbPass}')
    cursor = conn.cursor()
except:
    print(json.dumps({"error": "Failed to establish DB connection"}))
    sys.exit(1)

total_size = os.path.getsize(file_path)
rows = []

# Critical operation (if this fails, analysis can't be displayed)
try:
    with PcapReader(file_path) as reader:
        row_limit = 1000
        query = """INSERT INTO packet 
            (
                analysis_id,
                packet_number,
                l3_protocol,
                src_ip, 
                dst_ip, 
                captured_packet_length,
                src_port, 
                dst_port,
                tcp_flag,
                flow,
                tcp_window,
                tcp_ack_number,
                tcp_seq_number,
                l4_protocol,
                timestamp,
                raw_hex
            ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""

        for index, pkt in enumerate(reader, start=1):
            result = analyze_packet(pkt, index)

            if result["layers"]["L4"].get("protocol") is not None and "TCP" in result["layers"]["L4"].get("protocol"):
                result = reassemble_flows(result)


            if index % 500 == 0:
                current_pos = reader.f.tell()
            
                progress = 0
                if total_size > 0:
                    progress = min(int((current_pos / total_size) * 100), 100)
            
                cursor.execute(
                    "UPDATE analysis_job SET progress_percentage = %s WHERE analysis_id = %s", 
                    (progress, analysis_id)
                )
                conn.commit()
            
            # Make a list of 1000 tuples and then commit to DB
            rows.append((
                analysis_id, 
                result["id"],
                result["layers"]["L3"].get("protocol"),
                result["layers"]["L3"].get("src"), 
                result["layers"]["L3"].get("dst"), 
                result["layers"]["L3"].get("length"),
                result["layers"]["L4"].get("src_port"), 
                result["layers"]["L4"].get("dst_port"),
                result["layers"]["L4"].get("flags"),
                result["flow"],
                result["layers"]["L4"].get("window"),
                result["layers"]["L4"].get("ack"),
                result["layers"]["L4"].get("seq"),
                result["layers"]["L4"].get("protocol"),
                result["timestamp"],
                result["hex_dump"]
            ))
            
            if len(rows) >= row_limit:
                psycopg2.extras.execute_batch(cursor, query, rows)
                conn.commit()
                rows.clear()

    if len(rows) > 0:
        psycopg2.extras.execute_batch(cursor, query, rows)
        conn.commit()

    cursor.execute(
        "UPDATE analysis_job SET progress_percentage = 100 WHERE analysis_id = %s", 
        (analysis_id,)
    )
    conn.commit()
    # Slight delay, so 100% completion appears in the UI
    sleep(0.5)
    cursor.execute(
        "UPDATE analysis_job SET status = %s WHERE analysis_id = %s", 
        ("finished", analysis_id)
    )
    conn.commit()

except Exception as e:
    cursor.close()
    conn.close()
    raise e

# Optional operation (if this fails L3-L4 information will be displayed in the analysis)
try:
    tshark_process = create_tshark(file_path)
    tshark_stream = create_tshark_stream(tshark_process)

    query = """
        UPDATE packet set l7_attributes = %s WHERE analysis_id = %s AND packet_number = %s
    """
    rows.clear()
    for pkt in tshark_stream:
        rows.append((
            json.dumps(pkt["layers"]["L7"]),
            analysis_id,
            pkt["id"]
        ))
        if len(rows) >= row_limit:
            psycopg2.extras.execute_batch(cursor, query, rows)
            conn.commit()
            rows.clear()

    if len(rows) > 0:
        psycopg2.extras.execute_batch(cursor, query, rows)
        conn.commit()

except Exception as e:
        tshark_process.kill()
        tshark_process.wait()
        cursor.close()
        conn.close()
        raise e



tshark_process.wait()
# Close DB connection
cursor.close()
conn.close()