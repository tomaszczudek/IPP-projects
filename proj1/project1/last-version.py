import sys
import lark
import argparse
import xml.etree.ElementTree as ET
import xml.dom.minidom
import re

parser_arg = argparse.ArgumentParser(
    description=(
        "Analyzátor kódu v SOL25 (parse.py)."
        "Skript načte zdrojový kód v SOL25 ze standardního vstupu, "
        "zkontroluje lexikální, syntaktickou a statickou sémantickou správnost kódu "
        "a vypíše XML reprezentaci abstraktního syntaktického stromu (AST)."
    ),
    add_help=False
)

parser_arg.add_argument("-h", "--help", action="store_true", help="Zobrazí tuto nápovědu.")

try:
    args = parser_arg.parse_args()
except SystemExit:
    print("Error: Invalid arguments", file=sys.stderr)
    sys.exit(10)

if args.help:
    if len(sys.argv) > 2:
        print("Error: Invalid arguments", file=sys.stderr)
        sys.exit(10)
    parser_arg.print_help()
    sys.exit(0)


grammar = r'''
    program: classdec* | 
    classdec: "class" CID ":" CID "{" method* "}"
    method: selector block
    
    selector: ID | IDD+

    block: "[" block_par "|" block_stat* "]"
    block_par: IID*
    block_stat: ID ":=" expr "." 

    expr: expr_base ID | expr_base (IDD expr_base)*
    expr_base: INT | STR | ID | CID | block | "(" expr ")"

    ID: /[a-z_][a-zA-Z0-9_]*/
    IDD: /[a-z_][a-zA-Z0-9_]*[:]/
    IID: /[:][a-z_][a-zA-Z0-9_]*/

    CID: /[A-Z][a-zA-Z0-9]*/
    INT: /[+-]?(0|[1-9][0-9]*)/
    
    STR: /[']([^\\'\n\x00-\x1F]|\\'|\\n|\\\\)*[']/

    %ignore /\s+/
'''

try:
    input_code = sys.stdin.read().strip()
except (OSError, IOError):
    print("Error: Cannot read from stdin", file=sys.stderr)
    sys.exit(11) 

comments = re.findall(r'"(.*?)"', input_code, re.DOTALL)
input_code = re.sub(r'"(.*?)"', ' ', input_code, flags=re.DOTALL)

try:
    parser = lark.Lark(grammar, start='program', parser="lalr")
    ast_tree = parser.parse(input_code)
except lark.exceptions.UnexpectedCharacters:
    print("Error: Lexical Error", file=sys.stderr)
    sys.exit(21)
except lark.exceptions.UnexpectedInput:
    print("Error: Syntax Error", file=sys.stderr)
    sys.exit(22)
except Exception:
    print("Error: Unexpected Error", file=sys.stderr)
    sys.exit(99)

def parse_program(program_node):
    if len(comments) > 0:
        xml_elem = ET.Element("program", language="SOL25", description=comments[0].replace('\n', '&#10;').replace('"', '&quot;'))
    else:
        xml_elem = ET.Element("program", language="SOL25")

    if program_node.children:
        for i in range(len(program_node.children)):
            xml_elem.append(parse_class_dec(program_node.children[i]))
    
    return xml_elem

def parse_class_dec(class_node):
    global class_name
    class_name = class_node.children[0].value
    xml_elem = ET.Element("class", name=class_node.children[0].value, parent=class_node.children[1].value)
    
    if any(class_dec[0] == class_node.children[0].value for class_dec in class_info):
        print(f"Error: Redefinition of class '{class_node.children[0].value}' detected.", file=sys.stderr)
        sys.exit(35)
    
    if class_node.children[0].value in built_in:
        print(f"Error: Cannot redefine built-in class '{class_node.children[0].value}'.", file=sys.stderr)
        sys.exit(35)

    #if class_node.children[1].value not in built_in and not any(class_dec[0] == class_node.children[1].value for class_dec in class_info):
        #print(f"Error: Use of undefined class '{class_node.children[1].value}'.", file=sys.stderr)
        #sys.exit(32)

    class_info.append([class_node.children[0].value, class_node.children[1].value])

    for i in range(2, len(class_node.children)):
        xml_elem.append(parse_method(class_node.children[i]))
    return xml_elem

def parse_method(method_node):
    selector_name = "".join(str(child) for child in method_node.children[0].children)
    arity = selector_name.count(":")

    if arity != len(method_node.children[1].children[0].children):
        print(f"Error: Invalid number of parameters in method '{selector_name}' of class '{class_name}'", file=sys.stderr)
        sys.exit(33)

    if selector_name in key_words:
        print(f"Error: Cannot define method with key word '{selector_name}'.", file=sys.stderr)
        sys.exit(22)

    # Maybe methods parametrs has to be the same or idk 
    if selector_name in methods.get(class_name, {}):
        print(f"Error: Redefinition of method '{selector_name}' detected in class '{class_name}'.", file=sys.stderr)
        sys.exit(35)
    methods.setdefault(class_name, {})[selector_name] = arity

    formal_vars, local_vars = [], []
    xml_elem = ET.Element("method", selector=selector_name)
    xml_elem.append(parse_block(method_node.children[1], arity, formal_vars, local_vars))

    colision_vars[selector_name] = [formal_vars, local_vars]

    return xml_elem

def parse_block(block_node, arity, formal_vars, local_vars):
    xml_elem = ET.Element("block", arity=str(arity))
    
    for i in range(len(block_node.children[0].children)):
        param_name = str(block_node.children[0].children[i])[1:]

        if param_name in key_words:
            print(f"Error: Cannot define formal parameter with key word '{param_name}'.", file=sys.stderr)
            sys.exit(22)
        
        if param_name in formal_vars:
            print(f"Error: Redefinition of formal parameter '{param_name}' detected.", file=sys.stderr)
            sys.exit(35)
        
        formal_vars.append(param_name)
        xml_elem.append(ET.Element("parameter", name=param_name, order=str(i+1)))

    for i in range(1, len(block_node.children)):
        xml_elem.append(parse_block_stat(block_node.children[i], i, local_vars, formal_vars))

    return xml_elem

def parse_block_stat(block_stat_node, order, local_vars, formal_vars):
    xml_elem = ET.Element("assign", order=str(order))
    var_name = str(block_stat_node.children[0])
    if var_name in key_words:
        print(f"Error: Cannot insert value into key word '{var_name}'.", file=sys.stderr)
        sys.exit(22)
        
    xml_elem.append(ET.Element("var", name=var_name))
    xml_elem.append(parse_expr(block_stat_node.children[1], local_vars + formal_vars))
    local_vars.append(var_name)

    return xml_elem

def parse_expr(expr_node, local_v):
    xml_elem = ET.Element("expr")
    
    # Simple case - just a base expression
    if len(expr_node.children) == 1:
        child = expr_node.children[0]
        
        # Bezpečný přístup k potomkům - ověření typu
        if isinstance(child, lark.tree.Tree) and hasattr(child, 'children') and len(child.children) > 0:
            # Je to strom s potomky
            if isinstance(child.children[0], lark.lexer.Token):
                # Direct token - ID, INT, STR, CID
                if child.children[0].type == 'ID':
                    if child.children[0].value in ['nil', 'true', 'false']:
                        class_map = {'nil': 'Nil', 'true': 'True', 'false': 'False'}
                        xml_elem.append(ET.Element("literal", attrib={"class": str(class_map[child.children[0].value])}, value=str(child.children[0].value)))
                    else:
                        if child.children[0].value not in local_v and child.children[0].value not in pseaudo_var_sel:
                            print(f"Error: Undefined variable '{child.children[0].value}' used in expression.", file=sys.stderr)
                            sys.exit(32)
                        xml_elem.append(ET.Element("var", name=str(child.children[0].value)))
                elif child.children[0].type == 'INT':
                    xml_elem.append(ET.Element("literal", attrib={"class": "Integer"}, value=str(child.children[0].value)))
                elif child.children[0].type == 'STR':
                    # Process string literal (remove quotes and handle escapes)
                    string_value = child.children[0].value[1:-1].replace('\n', '&#10;').replace('"', '&quot;')
                    xml_elem.append(ET.Element("literal", attrib={"class": "String"}, value=string_value))
                elif child.children[0].type == 'CID':
                    # Definice třídy
                    xml_elem.append(ET.Element("literal", attrib={"class": "class"}, value=str(child.children[0].value)))
            elif child.data == 'block':
                # Block literal
                block_arity = len(child.children[0].children) if hasattr(child.children[0], 'children') else 0
                block_formal_vars, block_local_vars = [], []
                xml_elem.append(parse_block(child, block_arity, block_formal_vars, block_local_vars))
            elif child.data == 'expr':
                # Parenthesized expression
                inner_expr = parse_expr(child, local_v)
                for elem in inner_expr:
                    xml_elem.append(elem)
        elif isinstance(child, lark.lexer.Token):
            # Přímý token
            if child.type == 'ID':
                if child.value in ['nil', 'true', 'false']:
                    class_map = {'nil': 'Nil', 'true': 'True', 'false': 'False'}
                    xml_elem.append(ET.Element("literal", attrib={"class": str(class_map[child.value])}, value=str(child.value)))
                else:
                    if child.value not in local_v and child.value not in pseaudo_var_sel:
                        print(f"Error: Undefined variable '{child.value}' used in expression.", file=sys.stderr)
                        sys.exit(32)
                    xml_elem.append(ET.Element("var", name=str(child.value)))
            elif child.type == 'INT':
                xml_elem.append(ET.Element("literal", attrib={"class": "Integer"}, value=str(child.value)))
            elif child.type == 'STR':
                string_value = child.value[1:-1].replace('\n', '&#10;').replace('"', '&quot;')
                xml_elem.append(ET.Element("literal", attrib={"class": "String"}, value=string_value))
            elif child.type == 'CID':
                #print("i in CID")
                #if child.value not in built_in and not any(class_dec[0] == child.value for class_dec in class_info):
                    #print(f"Error: Undefined class '{child.value}' used in expression.", file=sys.stderr)
                    #sys.exit(32)
                expr_class.append(child.value)
                xml_elem.append(ET.Element("literal", attrib={"class": "class"}, value=str(child.value)))

    
    # Message send
    else:
        # Check if it's a unary message or a message with parameters
        if len(expr_node.children) == 2 and isinstance(expr_node.children[1], lark.lexer.Token) and expr_node.children[1].type == 'ID':
            # Unary message
            selector = expr_node.children[1].value
            if selector in key_words:
                print(f"Error: Cannot use key word '{selector}' as message selector.", file=sys.stderr)
                sys.exit(22)
            #print("i am in send")
            send_elem = ET.Element("send", selector=selector)
            expr_selectors.append([class_name, selector])
            # Add receiver expression
            receiver_expr = ET.Element("expr")
            
            # Parse the receiver (first child)
            if isinstance(expr_node.children[0], lark.lexer.Token):
                if expr_node.children[0].type == 'ID':
                    if expr_node.children[0].value not in local_v and expr_node.children[0].value not in pseaudo_var_sel:
                        print(f"Error: Undefined variable '{expr_node.children[0].value}' used in expression.", file=sys.stderr)
                        sys.exit(32)
                    receiver_expr.append(ET.Element("var", name=expr_node.children[0].value))
                elif expr_node.children[0].type == 'INT':
                    receiver_expr.append(ET.Element("literal", attrib={"class": "Integer"}, value=expr_node.children[0].value))
                elif expr_node.children[0].type == 'STR':
                    string_value = expr_node.children[0].value[1:-1].replace('\n', '&#10;').replace('"', '&quot;')
                    receiver_expr.append(ET.Element("literal", attrib={"class": "String"}, value=string_value))
                elif expr_node.children[0].type == 'CID':
                    receiver_expr.append(ET.Element("literal", attrib={"class": "class"}, value=expr_node.children[0].value))
            else:
                # It's a tree, parse recursively
                receiver_result = parse_expr(expr_node.children[0], local_v)
                for child in receiver_result:
                    receiver_expr.append(child)
            
            send_elem.append(receiver_expr)
            xml_elem.append(send_elem)
        else:
            # Message with parameters
            selector_parts = []
            args = []
            
            i = 1
            while i < len(expr_node.children):
                if isinstance(expr_node.children[i], lark.lexer.Token) and expr_node.children[i].type == 'IDD':
                    selector_parts.append(expr_node.children[i].value)
                    i += 1
                    if i < len(expr_node.children):
                        args.append(expr_node.children[i])
                        i += 1
                    else:
                        print("Error: Missing argument for message selector part", file=sys.stderr)
                        sys.exit(35)
                else:
                    break
            
            # Create send element with complete selector
            selector = "".join(selector_parts)
            send_elem = ET.Element("send", selector=selector)
            expr_selectors.append([class_name, selector])
            # Add receiver expression
            receiver_expr = ET.Element("expr")
            
            # Parse the receiver (first child)
            if isinstance(expr_node.children[0], lark.lexer.Token):
                if expr_node.children[0].type == 'ID':
                    if expr_node.children[0].value not in local_v and expr_node.children[0].value not in pseaudo_var_sel:
                        print(f"Error: Undefined variable '{expr_node.children[0].value}' used in expression.", file=sys.stderr)
                        sys.exit(32)
                    receiver_expr.append(ET.Element("var", name=expr_node.children[0].value))
                elif expr_node.children[0].type == 'INT':
                    receiver_expr.append(ET.Element("literal", attrib={"class": "Integer"}, value=expr_node.children[0].value))
                elif expr_node.children[0].type == 'STR':
                    string_value = expr_node.children[0].value[1:-1].replace('\n', '&#10;').replace('"', '&quot;')
                    receiver_expr.append(ET.Element("literal", attrib={"class": "String"}, value=string_value))
                elif expr_node.children[0].type == 'CID':
                    receiver_expr.append(ET.Element("literal", attrib={"class": "class"}, value=expr_node.children[0].value))
            else:
                # It's a tree, parse recursively
                receiver_result = parse_expr(expr_node.children[0], local_v)
                for child in receiver_result:
                    receiver_expr.append(child)
            
            send_elem.append(receiver_expr)
            
            # Add arguments
            for idx, arg in enumerate(args):
                arg_elem = ET.Element("arg", order=str(idx+1))
                arg_expr = parse_expr(arg, local_v)
                arg_elem.append(arg_expr)
                send_elem.append(arg_elem)
            
            xml_elem.append(send_elem)
    
    return xml_elem


def check_semantics():
    if not any(class_name == "Main" for class_name, _ in class_info):
        print("Error: Missing class 'Main'", file=sys.stderr)
        sys.exit(31)

    if methods.get("Main", {}).get("run") != 0:
        print("Error: Class 'Main' must have method 'run' with arity 0", file=sys.stderr)
        sys.exit(31)
    
    for method, (formal_vars, local_vars) in colision_vars.items():
        common_vars = set(formal_vars) & set(local_vars)
        if common_vars:
            print(f"Error: Variable name collision '{common_vars}' in method '{method}'.", file=sys.stderr)
            sys.exit(34)

    class_dict = {class_name: parent for class_name, parent in class_info}
    for class_name, parent in class_info:
        seen = set()
        while parent not in built_in:
            if parent in seen:
                print(f"Error: Circular inheritance detected involving class '{parent}'.", file=sys.stderr)
                sys.exit(35)
            seen.add(parent)
            if parent not in class_dict:
                print(f"Error: Undefined parent class '{parent}' for class '{class_name}'.", file=sys.stderr)
                sys.exit(32)
            parent = class_dict[parent]

    for class_name, parent in class_dict.items():
        while parent not in built_in:
            if parent not in class_dict:
                print(f"Error: Class '{class_name}' has an undefined parent '{parent}'.", file=sys.stderr)
                sys.exit(32)
            parent = class_dict[parent]

    for class_name in expr_class:
        if class_name not in {cls[0] for cls in class_info} and class_name not in built_in:
            print(f"Error: Undefined class '{class_name}' used in expression.", file=sys.stderr)
            sys.exit(32)
    
    for class_name, expr_selector in expr_selectors:
        while class_name:
            if expr_selector in methods.get(class_name, {}) or (class_name in built_in and expr_selector in built_in[class_name]):
                break
            if class_name in built_in:  # Pokud jsme v built-in třídě, už nemůžeme jít výš
                print(f"Error: Undefined method '{expr_selector}' used in class '{class_name}'.", file=sys.stderr)
                sys.exit(32)
            if class_name not in class_dict:
                print(f"Error: Undefined method '{expr_selector}' used in class '{class_name}'.", file=sys.stderr)
                sys.exit(32)
            class_name = class_dict[class_name]  # Posun do rodiče
        
key_words = {"class", "self", "super", "nil", "true", "false"}
built_in = {
    # {Class: [instance methods]}
    "Object": ["new", "from:", "identicalTo:", "equalTo:", "asString", "isNumber", "isString", "isBlock", "isNil"], 
    "Nil": ["new", "from:", "asString"],
    "Integer": ["new", "from:", "equalTo:", "greaterThan:", "plus:", "minus:", "multiplyBy:", "divBy:", "asString", "asInteger", "timesRepeat:"],
    "String": ["new", "from:", "read", "print", "equalTo:", "asString", "asInteger", "concateWith:", "startsWith:", "endsWith:"],
    "Block": ["new", "from:", "whileTrue:"],
    "True": ["new", "from:", "not", "and:", "or:", "ifTrue:", "ifFalse:"],
    "False": ["new", "from:", "not", "and:", "or:", "ifTrue:", "ifFalse:"]
}

for class_methods in built_in:
    if class_methods != "Object":
        built_in[class_methods] = built_in[class_methods] + built_in["Object"]

class_name = None
pseaudo_var_sel = ["self", "super", "nil", "true", "false"]
# [[class_name, parent_class_name]]
class_info = []
# {class_name: {selector_name: arity}}
methods = {}
# send selectors [class_name, selector_name] 
expr_selectors = []
expr_class = []
# {method_name: [[formal_var], [local_var]]}
colision_vars = {}

#print(ast_tree.pretty())
document = parse_program(ast_tree)
check_semantics()
xml_str = ET.tostring(document, encoding="utf-8").decode("utf-8")
pretty_xml = xml.dom.minidom.parseString(xml_str).toprettyxml(indent="  ")
pretty_xml = re.sub(r'(</method>)(?=.*</method>)', r'\1\n', pretty_xml, flags=re.DOTALL)
pretty_xml = pretty_xml.replace("/>", " />").replace("\'", "&apos;").replace("&amp;quot;", "&quot;").replace("&amp;#10;", "&#10;")
pretty_xml = pretty_xml.replace("<?xml version=\"1.0\" ?>", "<?xml version=\"1.0\" encoding=\"UTF-8\"?>")

try:
    print(pretty_xml, flush=True)
except (OSError, IOError):
    print("Error: Cannot write to stdout", file=sys.stderr)
    sys.exit(12)
