# Used for translating message codes into readable format when there is a possibility for > 1 code
def translate_message(splitter, context, message_codes):
    list = ""
    codes = message_codes.split(splitter)

    for code in codes:
        readable_context = context.get(code)
        if readable_context:
            list += readable_context + " "
    return list