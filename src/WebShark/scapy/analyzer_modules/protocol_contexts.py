# Translate raw values to easier understandable ones
tls_name_versions = {
    "0x0304": "TLS1.3",
    "0x0303": "TLS1.2",
}

# There are many more message types (https://en.wikipedia.org/wiki/Dynamic_Host_Configuration_Protocol#DHCP_message_types), these are the basic ones
dhcp_message_type = {
    "1": "(1) DHCPDISCOVER",
    "2": "(2) DHCPOFFER",
    "3": "(3) DHCPREQUEST",
    "5": "(5) DHCPACK"
}

# There are many more (https://en.wikipedia.org/wiki/Dynamic_Host_Configuration_Protocol#Options), these are the ones encountered in samples 
dhcp_request_list = {
    "1": "Subnet Mask - ",
    "3": "Router - ",
    "6": "DNS - ",
    "42": "NTP - "
}

# tshark retruns True for client side, false for server side
ssh_direction = {
    "True": "client-to-server",
    "False": "server-to-client"
}

# General message codes https://datatracker.ietf.org/doc/html/rfc4253#section-12. For KEX packets tshark/wireshark has custom messages, only encountered in samples are defined here.
ssh_message_codes = {
    "1": "Disconnect (1)",
    "20": "Key exchange init (20)",
    "21": "New keys (21)",
    "31": "Elliptic Curve Diffie-Hellman Key Exchange Reply (31)",
    "30": "Elliptic Curve Diffie-Hellman Key Exchange Reply (31)"

}