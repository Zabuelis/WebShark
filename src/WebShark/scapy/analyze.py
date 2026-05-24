import sys
import os
import psycopg2
import psycopg2.extras
import json
from time import sleep
import subprocess
import csv
from analyzer_modules import *
csv.field_size_limit(sys.maxsize)

# So, in our case we have layers: L3, L4 and L7. Each layer has its own registry.
# The registry is a dict that maps the protocol name to the function that can handle it.
# A dict is a data structure that maps keys to values (like hashmap in other languages).
# in our case dict that maps: layer -> protocol name -> function.

# Define protocol fields for retreival. All of them can be found here https://www.wireshark.org/docs/dfref/
tshark_protocols = {
    # IPv4 fields
    "ip": [ "ip.src", "ip.dst", "ip.ttl" ],
    # IPv6 fields
    "ipv6": [ "ipv6.src", "ipv6.dst", "ipv6.hlim", "ipv6.nxt" ],
    # ARP fields
    "arp": [ "arp.src.proto_ipv4", "arp.src.hw_mac", "arp.dst.hw_mac", "arp.dst.proto_ipv4", "arp.opcode", "arp.proto.type" ],
    # TCP fields
    "tcp": [ "tcp.srcport", "tcp.dstport", "tcp.seq", "tcp.ack", "tcp.flags.str", "tcp.window_size" ],
    # UDP fields
    "udp": [ "udp.srcport", "udp.dstport", "udp.length" ],
    # ICMP fields
    "icmp": [ "icmp.type", "icmp.code", "icmp.checksum" ],
    # HTTP 1/1.1 fields
    "http": [ "http.request.version", "http.authorization", "http.response.version", "http.request.method", "http.request.uri", "http.request.full_uri", "http.response.code", "http.response.phrase", "http.user_agent", "http.connection", "http.response.phrase", "http.file_data", "http.content_length"],
    # DNS fields
    "dns": [ "dns.id", "dns.flags", "dns.flags.response", "dns.qry.name", "dns.qry.type", "dns.resp.name", "dns.resp.type" ],
    # DHCPv4 fields
    "dhcp": [ "dhcp.id", "dhcp.ip.client", "dhcp.ip.relay", "dhcp.ip.server", "dhcp.ip.your",  "dhcp.option.dhcp", "dhcp.option.subnet_mask", "dhcp.option.request_list_item"],
    # TLS fields
    "tls": [ "tls.app_data_proto", "tls.handshake.type", "tls.record.content_type", "tls.app_data", "tls.record.version", "tls.record.length" ],
    # SSH fields
    "ssh" : [ "ssh.protocol", "ssh.direction", "ssh.encrypted_packet", "ssh.packet_length", "ssh.packet_length_encrypted", "ssh.message_code" ]
}

# Tshark stream is formatted and read as CSV
field_separator = "\u001f"   # Special ascii character Unit Separator

# Flow cache dict, stores (src_ip, dst_ip, src_port, dst_port) tuple as a key
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
def handle_ipv4(packet):
    return {
        "protocol": "IPv4",
        "l3_attributes": {
            "Source_IP": packet.get("ip.src"),
            "Destination_IP": packet.get("ip.dst"),
            "TTL": packet.get("ip.ttl"),
        }
    }

def handle_ipv6(packet):
    return {
        "protocol": "IPv6",
        "l3_attributes": {
            "Source_IP": packet.get("ipv6.src"),
            "Destination_IP": packet.get("ipv6.dst"),
            "Hop_Limit": packet.get("ipv6.hlim"),
            "Next_Header": packet.get("ipv6.nxt"),
        }
    }

def handle_arp(packet):
    return {
        "protocol": "ARP",
        "l3_attributes": {
            "Source_IP": packet.get("arp.src.proto_ipv4"),
            "Destination_IP": packet.get("arp.dst.proto_ipv4"),
            "Mac_Src": packet.get("arp.src.hw_mac"),
            "Mac_Dst": packet.get("arp.dst.hw_mac"),
            "Proto_Type": packet.get("arp.proto.type"),
            "Opcode": protocol_contexts.arp_opcode.get(int(packet.get("arp.opcode")))
        }
    }

register("L3", "IPv4", handle_ipv4)
register("L3", "IPv6", handle_ipv6)
register("L3", "ARP", handle_arp)

# L4 handlers
def handle_tcp(packet):
    return {
        "protocol": "TCP",
        "l4_attributes": {
            "Source_Port": packet.get("tcp.srcport"),
            "Destination_Port": packet.get("tcp.dstport"),
            "Seq": packet.get("tcp.seq"),
            "Ack": packet.get("tcp.ack"),
            "Flags": packet.get("tcp.flags.str"),
            "Window": packet.get("tcp.window_size"),
        }
    }

def handle_udp(packet):
    return {
        "protocol": "UDP",
        "l4_attributes": {
            "Source_Port": packet.get("udp.srcport"),
            "Destination_Port": packet.get("udp.dstport"),
            "Length": packet.get("udp.length"),
        }
    }

def handle_icmp(packet):
    return {
        "protocol": "ICMP",
        "l4_attributes": {
            "Type": protocol_contexts.icmp_type.get(packet.get("icmp.type")),
            "Code": packet.get("icmp.code"),
            "Checksum": packet.get("icmp.checksum")
        }
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
    }

    if packet.get("http.file_data") is not None:
        payload = bytes.fromhex(packet.get("http.file_data")).decode("utf-8")
        # Sanitize payload to avoid psycopg untranslatable chars, so far encountered only these
        payload = payload.replace("\u0000", "")
        http_header["Payload"] = payload
    
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
        "Encrypted_Content": packet.get("tls.app_data"),
        "Content_Type": packet.get("tls.record.content_type"),
        "Handshake_Type": packet.get("tls.handshake.type")
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

# Create a subprocess (same as fork) that runs tshark
# Subprocess uses a pipe to receive data from tshark (only a limited amount of data is stored in the memory).
def create_tshark(file_path):
    # Construct argument string of required fields
    analysis_fields = []
    if tshark_protocols:
        for protocol in tshark_protocols:
            if protocol:
                protocol_fields = tshark_protocols.get(protocol)
                if protocol_fields:
                        for field in protocol_fields:
                            analysis_fields.extend(["-e", field])

    try:
        tshark_process = subprocess.Popen(["tshark", "-r", file_path,
            # "-Y", filter_fields,    # Only return defined protocols
            "-T", "fields", # Field format
            "-e", "frame.number",   # Return specified fields only
            *analysis_fields,  
            "-e", "frame.time_epoch",
            "-e", "frame.len",
            "-E", f"separator={field_separator}",   # Separate fields with a specified separator
            "-E", "quote=d",
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

    # Parse other packets, one by one as CSV
    csv_reader = csv.DictReader(tshark_process.stdout, fieldnames=header, delimiter=field_separator)
    for packet in csv_reader:
        yield analyze_packet(packet)

# Identify which protocol a packet uses by looking at its layers
def identify_l3(packet):
    if packet.get("ip.src"):
        return "IPv4"
    elif packet.get("ipv6.src"):
        return "IPv6"
    elif packet.get("arp.src.proto_ipv4"):
        return "ARP"
    return None

def identify_l4(packet):
    if packet.get("tcp.srcport"):
        return "TCP"
    elif packet.get("udp.srcport"):
        return "UDP"
    elif packet.get("icmp.type"):
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
def analyze_packet(packet):
    result = {
        "id": int(packet.get("frame.number")),
        "length": int(packet.get("frame.len")),
        "timestamp": float(packet.get("frame.time_epoch")),
        "flow": None,
        "layers": {
            "L3": {},
            "L4": {},
            "L7": {},
        },
    }

    # L3
    l3_name = identify_l3(packet)
    if l3_name and l3_name in handlers["L3"]:
        result["layers"]["L3"] = handlers["L3"][l3_name](packet)

    # L4
    l4_name = identify_l4(packet)
    if l4_name and l4_name in handlers["L4"]:
        result["layers"]["L4"] = handlers["L4"][l4_name](packet)

    l7_name = identify_l7(packet)
    if l7_name and l7_name in handlers["L7"]:
        result["layers"]["L7"] = handlers["L7"][l7_name](packet)
    
    return result

# Asigns TCP packets to a specific flow, based on flow_cache dict
# Creates new TCP flows upon syn flag detection
# Finishes TCP flows upon fin flag detection
# If scapy will be retired, tshark returns different flag names, they should be changed here.
def reassemble_flows(pkt):
    flags = pkt["layers"]["L4"]["l4_attributes"].get("Flags")
    global flow_num

    # Construct a key from IPs and PORTs, prioritize lower port as message source
    # Lower port has no real impact, only normalizes the key creation.
    if pkt["layers"]["L4"]["l4_attributes"].get("Source_Port") < pkt["layers"]["L4"]["l4_attributes"].get("Destination_Port"):
        src_ip = pkt["layers"]["L3"]["l3_attributes"].get("Source_IP")
        dst_ip = pkt["layers"]["L3"]["l3_attributes"].get("Destination_IP")
        src_port = pkt["layers"]["L4"]["l4_attributes"].get("Source_Port")
        dst_port = pkt["layers"]["L4"]["l4_attributes"].get("Destination_Port")
    else:
        dst_ip = pkt["layers"]["L3"]["l3_attributes"].get("Source_IP")
        src_ip = pkt["layers"]["L3"]["l3_attributes"].get("Destination_IP")
        dst_port = pkt["layers"]["L4"]["l4_attributes"].get("Source_Port")
        src_port = pkt["layers"]["L4"]["l4_attributes"].get("Destination_Port")

    key = (src_ip, dst_ip, src_port, dst_port)
    sender = (pkt["layers"]["L3"]["l3_attributes"].get("Source_IP"), pkt["layers"]["L4"]["l4_attributes"].get("Source_Port"))

    # S (SYN) flag indicates the beginning of a TCP session
    if "S" in flags and "A" not in flags:
        flow_cache[key] = {
            "id": flow_num,
            "initiator": sender,
            "receiver": (pkt["layers"]["L3"]["l3_attributes"].get("Destination_IP"), pkt["layers"]["L4"]["l4_attributes"].get("Destination_Port")),
            "initiator_fin": False,
            "receiver_fin": False,
        }
        pkt["flow"] = flow_num
        flow_num += 1
    elif flow_cache.get(key) is not None:
        flow = flow_cache[key]
        pkt["flow"] = flow["id"]
        # R (RST) flag indicates to break the connection right at this moment
        if "R" in flags:
            flow_cache.pop(key)
        # F (FIN) flag indicates that one side wants to close the connection
        elif "F" in flags:
            if sender == flow["receiver"]:
                flow_cache[key]["receiver_fin"] = True
            elif sender == flow["initiator"]:
                flow_cache[key]["initiator_fin"] = True
        # If both sides sent FIN wait for last ACK and then remove flow from cache
        elif flow["initiator_fin"] is True and flow["receiver_fin"] is True and "A" in flags:
            flow_cache.pop(key)
            
    # Packets that are not SYN and have no record in the flow cache
    # are interpreted as having no beginning recorded. In this case they also get a unique flow.
    else:
        flow_cache[key] = {
            "id": flow_num,
            "initiator": sender,
            "receiver": (pkt["layers"]["L3"]["l3_attributes"].get("Destination_IP"), pkt["layers"]["L4"]["l4_attributes"].get("Destination_Port")),
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

cursor.execute("UPDATE analysis_job SET status = %s WHERE analysis_id = %s", ("analyzing", analysis_id))
conn.commit()
total_size = os.path.getsize(file_path)
current_pos = 0
rows = []

# Critical operation (if this fails, analysis can't be displayed)
try:
    tshark_process = create_tshark(file_path)
    tshark_stream = create_tshark_stream(tshark_process)
    row_limit = 1000
    query = """INSERT INTO packet 
        (
            analysis_id,
            packet_number,
            l3_protocol,
            l3_attributes,
            l4_protocol,
            l4_attributes,
            flow,
            captured_packet_length,
            timestamp,
            l7_attributes
        ) VALUES (%s, %s, %s, %s, %s, %s, %s, %s, %s, %s)"""

    for packet in tshark_stream:
        current_pos += packet.get("length")
        if packet["layers"]["L4"].get("protocol") is not None and "TCP" in packet["layers"]["L4"].get("protocol"):
            packet = reassemble_flows(packet)

        if packet.get("id") % 500 == 0:
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
            packet["id"],
            packet["layers"]["L3"].get("protocol"),
            json.dumps(packet["layers"]["L3"].get("l3_attributes")),
            packet["layers"]["L4"].get("protocol"),
            json.dumps(packet["layers"]["L4"].get("l4_attributes")),
            packet["flow"],
            packet.get("length"),
            packet.get("timestamp"),
            json.dumps(packet["layers"]["L7"]) 
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
    tshark_process.kill()
    tshark_process.wait()
    cursor.close()
    conn.close()
    raise e

tshark_process.wait()
# Close DB connection
cursor.close()
conn.close()