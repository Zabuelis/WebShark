import sys
import json
from scapy.all import PcapReader, IP, TCP, UDP, ICMP

file_path = sys.argv[1]

with PcapReader(file_path) as reader:
    for index, pkt in enumerate(reader):
        if index >= 10:
            break

        if IP not in pkt:
            continue

        # build L3 data
        l3 = {
            "protocol": "IPv4",
            "src": pkt[IP].src,
            "dst": pkt[IP].dst,
        }

        # build L4 data
        # we set l4 to None by default
        # if the packet has something in l4 - only then we overwrite it
        l4 = None

        if TCP in pkt:
            l4 = {
                "protocol": "TCP",
                "src_port": pkt[TCP].sport,
                "dst_port": pkt[TCP].dport,
            }
        elif UDP in pkt:
            l4 = {
                "protocol": "UDP",
                "src_port": pkt[UDP].sport,
                "dst_port": pkt[UDP].dport,
            }
        elif ICMP in pkt:
            l4 = {
                "protocol": "ICMP",
                "type": pkt[ICMP].type,
                "code": pkt[ICMP].code,
            }

        # build the full packet
        packet_data = {
            "id": index,
            "length": len(pkt),
            "layers": {
                "L3": l3,
                "L4": l4,
            },
        }

        # return one packet JSON instead of big JSON of all packets
        print(json.dumps(packet_data))