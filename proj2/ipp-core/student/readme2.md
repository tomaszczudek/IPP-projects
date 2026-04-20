# Implementační dokumentace k 2. úloze do IPP 2024/2025
**Jméno a příjmení**: Tomasz Czudek  
**Login**: xczudet00  

## Návrh řešení

Interpretace probíhá ve třech hlavních krocích:
1. **Parsování XML** – soubor je načten a zkontrolován na syntaktickou a lexikální správnost pomocí třídy `XMLParser`.
2. **Zpracování instrukcí** – jednotlivé instrukce jsou převedeny do interní reprezentace (`DOMProcessor`) a předány interpretu (`Interpreter`).
3. **Běh programu** – provádění instrukcí v rámci běhového prostředí řízeného pomocí zpráv a bloků.

## Interní reprezentace

- **`XMLParser.php`**: Odpovídá za bezpečné načtení a validaci XML dokumentu. Pokud je XML nesprávné, skript ukončí běh s odpovidajícím návratovým kódem.
- **`DOMProcessor.php`**: Převádí načtený XML dokument do datových struktur vhodných pro interpretaci.
- **`Interpreter.php`**: Provádí samotnou interpretaci instrukcí v přesném pořadí podle pořadového čísla (`order` atribut v XML).

## Funkčnost a postup interpretace

1. **Inicializace programu**
   - Načtení XML přes `XMLParser`.
   - Validace existence třídy `Main` a metody `run` s aritou 0.

2. **Příprava kontextu**
   - Vytvoření instance `Main` a inicializace pseudoproměnné `self`.
   - Mapování metod do `methodMap` s informacemi o parametrech a příkazech.

3. **Vyhodnocování výrazů**
   - Rekurzivní průchod instrukcemi pomocí `evaluateExpression()`.
   - Podpora pro:
     - Literály (`SOLInteger`, `SOLString`).
     - Proměnné a instanční atributy.
     - Zasílání zpráv s kontrolou arity.

4. **Zpracování bloků**
   - Vytváření `SOLBlock` s izolovaným kontextem proměnných.
   - Implementace řídicích struktur pomocí metod jako `whileTrue:` a podmíněných volání.

## Filosofie návrhu interpreta

- **Hlavní myšlenkou celého interpreta je práce s bloky (reprezentujícími metody tříd) a jejich spouštění v rámci běhového prostředí (runtime).**

Při spuštění interpretu dojde nejprve k načtení XML reprezentace programu, která je zpracována do vnitřních struktur. Tyto struktury obsahují informace o třídách, metodách (blocích), a jejich atributech. Každá metoda je reprezentována jako blok (`SOLBlock`), který obsahuje seznam parametrů a seznam příkazů, které se mají vykonat.

## Vnitřní reprezentace

1. **Po parsování je program interně reprezentován pomocí těchto struktur:**
- `classMap` – uchovává jména tříd a jejich definované metody.
- `methodMap` – obsahuje bloky reprezentující těla metod, jejich parametry a příkazy.
- `classAttributes` – statické proměnné přiřazené ke konkrétní třídě.
- `globals`, `locals` – globální a lokální proměnné aktivního kontextu (rámce).

   Každý kódový blok (`SOLBlock`) obsahuje:
   - seznam parametrů,
   - seznam instrukcí,

2. Každá metoda/blok je spouštěna až ve chvíli, kdy dojde k jejímu vyvolání. Interpret tedy není založen na průchodu stromem (např. AST), ale na řízeném volání bloků v kontextu objektů, ke kterému dochází v metodě `dispatchMessage`.

## Spouštění metod

Při volání zprávy na objekt (`dispatchMessage`) se kontroluje, zda se jedná o:
- vestavěnou třídu a její vestavěné metody,
- uživatelskou třídu (např. `Main`) a její metody,
- nebo přístup k proměnné/atr (pokud metoda není nalezena a selector neobsahuje `:`).

## Průběh interpretace

1. Ověření existence třídy `Main` a metody `run` s aritou 0.
2. Vytvoření nové instance třídy `Main` a zavolání metody `run`.
3. Rekurzivní interpretace výrazů:
   - zpracování literálů,
   - proměnné (lokální, globální, třídy),
   - bloky,
   - zprávy a kontrola arity.

4. Práce s bloky:
   - každý blok má vlastní lokální kontext a může být zanořený,
   - volání metod odpovídá předání zprávy.
---

## UML diagram tříd

```plaintext
 +-----------------------------+
 |          XMLParser          |
 |-----------------------------|
 | +parse(string): DOMDocument |
 +-----------------------------+
              |
              v
 +------------------------------+
 |         DOMProcessor         |
 |------------------------------|
 | +process(DOMDocument): array |
 +------------------------------+
              |
              v
 +-----------------------------+
 |         Interpreter         |
 |-----------------------------|
 | +run(array): int            |
 | +dispatchMessage(...)       |
 +-----------------------------+
