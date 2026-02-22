import sys
import json
from scapy.all import *

def analyze_pcap(file_path):
    try:
        # Read the pcap file
        packets = rdpcap(file_path)
        result = []

        # Analyze the first 100 packets
        for i, pkt in enumerate(packets[:100]):
            if IP in pkt:
                if DNS in pkt:
                    proto = "DNS"
                elif TCP in pkt:
                    proto = "TCP"
                elif UDP in pkt:
                    proto = "UDP"
                elif ICMP in pkt:
                    proto = "ICMP"
                else:
                    proto = "Unknown"

                result.append({
                    "id": i,
                    "protocol": proto,
                    "src": pkt[IP].src,
                    "dst": pkt[IP].dst,
                    "length": len(pkt),
                    "info": pkt.summary()
                })

        return json.dumps(result)
    except Exception as e:
        return json.dumps({"error": str(e)})

if __name__ == "__main__":
    # First argument is the path to the pcap file
    path = sys.argv[1]
    print(analyze_pcap(path))
