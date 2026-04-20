#python3.11 verze 3.11.2
PYTHON = python3.11
SCRIPT = parse.py
INPUT = example2.txt
OUTPUT = xml.xml
DOCUMENT = readme1.md

.PHONY: run install lint clean

run:
	$(PYTHON) $(SCRIPT) < $(INPUT) > $(OUTPUT) && echo $$?

zip:
	zip -r xczudet00.zip $(SCRIPT) $(DOCUMENT)

test:
	$(PYTHON) $(SCRIPT) < input.txt > test.txt && echo $$?
copy:
	cp $(SCRIPT) ../tests/

lint:
	flake8 $(SCRIPT)

clean:
	rm -rf __pycache__ $(VENV)
