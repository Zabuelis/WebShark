import sys
from scapy.all import PcapReader, IP, TCP, UDP, ICMP

file_path = sys.argv[1]

with PcapReader(file_path) as reader:
    for index, pkt in enumerate(reader):
        if index >= 10:
            break

        if IP not in pkt:
            print(f"Packet #{index} No IP layer found, skipping")
            print()
            continue

        # which L4 protocol?
        if TCP in pkt:
            proto = "TCP"
            ports = f"{pkt[TCP].sport} to {pkt[TCP].dport}"
        elif UDP in pkt:
            proto = "UDP"
            ports = f"{pkt[UDP].sport} to {pkt[UDP].dport}"
        elif ICMP in pkt:
            proto = "ICMP"
            ports = f"type={pkt[ICMP].type} code={pkt[ICMP].code}"
        else:
            proto = "Unknown"
            ports = "—"

        print(f"Packet #{index}")
        print(f"  {pkt[IP].src} to {pkt[IP].dst}")
        print(f"  Protocol: {proto}")
        print(f"  Ports:    {ports}")
        print(f"  Size:     {len(pkt)} bytes")
        print()