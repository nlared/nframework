# SAT XMLS Examples

These examples use classes generated in `sat/classes` and produce XML output.

## Files

- `01_cfdi40_comprobante.php`: creates a CFDI 4.0 Comprobante XML.
- `02_cartaporte31.php`: creates a Carta Porte 3.1 XML.
- `03_pagos20.php`: creates a Pagos 2.0 XML.
- `04_nomina12.php`: creates a Nomina 1.2 XML.
- `05_cfdi40_con_nomina12.php`: creates a CFDI 4.0 XML with Nomina 1.2 in `Complemento`.
- `06_lectura_xml.php`: reads CFDI XML, deserializes it into objects, and prints parsed fields.
- `07_tablas_isr_subsidio.php`: calculates ISR and subsidy using `sat/tablas.php`.
- `08_tablas_imss.php`: calculates IMSS quotas by year and risk class.
- `09_tablas_vigencias.php`: resolves active years by effective date for each tax table group.

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
php sat/examples/07_tablas_isr_subsidio.php
php sat/examples/07_tablas_isr_subsidio.php 2026 18500
php sat/examples/08_tablas_imss.php
php sat/examples/08_tablas_imss.php 2026 650 2
php sat/examples/09_tablas_vigencias.php
php sat/examples/09_tablas_vigencias.php 2024-02-15
```

Each command prints XML to stdout.
