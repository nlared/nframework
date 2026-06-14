# SAT XMLS Examples

These examples use classes generated in `sat/classes` and produce XML output.

## Files

- `01_cfdi40_comprobante.php`: creates a CFDI 4.0 Comprobante XML.
- `02_cartaporte31.php`: creates a Carta Porte 3.1 XML.
- `03_pagos20.php`: creates a Pagos 2.0 XML.
- `04_nomina12.php`: creates a Nomina 1.2 XML.
- `05_cfdi40_con_nomina12.php`: creates a CFDI 4.0 XML with Nomina 1.2 in `Complemento`.
- `06_lectura_xml.php`: reads CFDI XML, deserializes it into objects, and prints parsed fields.

## Run

From project root:

```bash
php sat/examples/01_cfdi40_comprobante.php
php sat/examples/02_cartaporte31.php
php sat/examples/03_pagos20.php
php sat/examples/04_nomina12.php
php sat/examples/05_cfdi40_con_nomina12.php
php sat/examples/06_lectura_xml.php
php sat/examples/06_lectura_xml.php /ruta/a/archivo.xml
```

Each command prints XML to stdout.
