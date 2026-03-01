import sys
import json
from scapy.all import PcapReader, IP, TCP, UDP, ICMP

# ToDo: Refactor comments? ↓
# So, in our case so far we have two layers: L3 and L4. Each layer has its own registry.
# The registry is a dict that maps the protocol name to the function that can handle it.
# A dict is a data structure that maps keys to values (like hashmap in other languages).
# in our case dict that maps: layer -> protocol name -> function.

handlers = {
    "L3": {},
    "L4": {},
}

# Add a handler function to the registry
def register(layer, name, func):
    handlers[layer][name] = func

# L3 handlers
def handle_ipv4(pkt):
    if IP not in pkt:
        return None
    return {
        "protocol": "IPv4",
        "src": pkt[IP].src,
        "dst": pkt[IP].dst,
    }


register("L3", "IPv4", handle_ipv4)

# L4 handlers
def handle_tcp(pkt):
    if TCP not in pkt:
        return None
    return {
        "protocol": "TCP",
        "src_port": pkt[TCP].sport,
        "dst_port": pkt[TCP].dport,
    }


def handle_udp(pkt):
    if UDP not in pkt:
        return None
    return {
        "protocol": "UDP",
        "src_port": pkt[UDP].sport,
        "dst_port": pkt[UDP].dport,
    }


def handle_icmp(pkt):
    if ICMP not in pkt:
        return None
    return {
        "protocol": "ICMP",
        "type": pkt[ICMP].type,
        "code": pkt[ICMP].code,
    }


register("L4", "TCP", handle_tcp)
register("L4", "UDP", handle_udp)
register("L4", "ICMP", handle_icmp)

# Identify which protocol a packet uses by looking at its layers
def identify_l3(pkt):
    if IP in pkt:
        return "IPv4"
    return None


def identify_l4(pkt):
    if TCP in pkt:
        return "TCP"
    if UDP in pkt:
        return "UDP"
    if ICMP in pkt:
        return "ICMP"
    return None


# Main function
def analyze_packet(pkt, index):
    result = {
        "id": index,
        "length": len(pkt),
        "layers": {
            "L3": None,
            "L4": None,
        },
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

with PcapReader(file_path) as reader:
    for index, pkt in enumerate(reader):
        result = analyze_packet(pkt, index)
        print(json.dumps(result))