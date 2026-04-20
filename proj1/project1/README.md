# Implementační dokumentace k 1. úloze do IPP 2024/2025
- **Jméno a příjmení**: Tomasz Czudek
- **Login** : xczudet00

## Analýza kódu v SOL25
Skript `parse.py` je analyzátor kódu jazyka SOL25, který zpracovává vstupní kód, kontroluje jeho lexikální, syntaktickou a sémantickou správnost a generuje XML reprezentaci abstraktního syntaktického stromu (AST).

### Architektura řešení

Řešení je založeno na následujících klíčových komponentách:

- **Lexikální a syntaktická analýza** - využívá knihovnu Lark pro definici gramatiky a parsování
- **Sémantická analýza** – kontroluje strukturu programu, dědičnost, existenci metod a tříd, správný počet argumentů a další sémantická pravidla
- **Generování XML výstupu** - vytváří XML reprezentaci AST pomocí knihovny ElementTree
- **Zpracování chyb** - v případě nalezení chyby skript ukončí běh s odpovídajícím návratovým kódem

### Implementační detaily

#### Zpracování vstupu

- Skript čte vstupní kód ze standardního vstupu
- Před parsováním jsou komentáře odstraněny pomocí regulárních výrazů
- Kód je parsován podle definované gramatiky jazyka SOL25

#### Parsování a vytváření AST

Hlavní funkcí je `parse_program`, která zpracovává kořenový uzel programu a volá další funkce pro zpracování jednotlivých částí kódu:
- `parse_class_dec` - zpracovává deklarace tříd
- `parse_method` - zpracovává definice metod
- `parse_block` - zpracovává bloky kódu
- `parse_expr` - zpracovává výrazy a volání zpráv

#### Sémantická kontrola
Při sémantické analýze jsem využil několik pomocných struktur:
- `key_words` – seznam rezervovaných slov
- `built_in` – vestavěné třídy a jejich metody
- `class_info` – seznam tříd a jejich nadřazených tříd.
- `methods`– mapování metod tříd a jejich arit
- `expr_selectors` – použité selektory ve výrazech.
- `expr_class` – seznam tříd použitých ve výrazech.
- `colision_vars` – sledování proměnných pro detekci kolizí

Tyto struktury slouží k ukládání klíčových informací potřebných pro syntaktickou a sémantickou analýzu kódu

Funkce `check_semantics` provádí následující kontroly:
- **Struktura programu** - musí existovat třída Main s metodou run a aritou 0.
- **Kolize identifikátorů** - detekce proměnných se stejným jménem v rámci jednoho rozsahu
- **Cyklická dědičnost** - dědičnost musí být acyklická
- **Použití nedefinovaných tříd a metod**  - odkazy na třídy a metody musí odpovídat deklaracím
    - kontroluje se, zda všechny třídy použité v kódu jsou deklarovány a mají platného rodiče
- **Správné počty parametrů metod** - počet argumentů musí odpovídat definici metody

### Implementované vlastnosti
- Zpracování argumentů
- Lexikální a syntaktická analýza
- Generování XML
- Statická sémantická kontrola

### Nedokončené vlastnosti
- Kontrola metodické sémantiky - není plně ověřeno, zda třídy správně dědí metody od svých nadřazených tříd
- Podpora vnořených a složitějších syntaktických konstrukcí výrazu

#### Ošetření chyb

Skript identifikuje a hlásí různé typy chyb pomocí odpovídajících návratových kódů:

- Lexikální chyby (21)
- Syntaktické chyby (22)
- Sémantické chyby (31-35)
- Chyby I/O a argumentů skriptu (10-12)
- Interní chyby (99)
