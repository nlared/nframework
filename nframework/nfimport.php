<?php

use MongoDB\BSON\UTCDateTime;
use PhpOffice\PhpSpreadsheet\IOFactory;

$tabla = $m->{$config['sitedb']}->nftables->findOne([
    'nfcollection' => $p['collection'],
]);

if (!$tabla) {
    echo 'No se encontró la tabla';
    return;
}
if (!isset($tabla->nffields) || count($tabla->nffields) === 0) {
    echo 'La tabla no tiene campos definidos';
    return;
}

function nfimportNormalizeKey(string $value): string
{
    $value = trim($value);
    if ($value === '') {
        return '';
    }
    $value = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
    $value = strtolower((string) $value);
    return preg_replace('/[^a-z0-9]/', '', $value);
}

function nfimportCleanHeader($header, int $index): string
{
    $name = trim((string) $header);
    if ($index === 0) {
        $name = preg_replace('/^\xEF\xBB\xBF/', '', $name);
    }
    if ($name === '') {
        return 'Columna ' . ($index + 1);
    }
    return $name;
}

function nfimportNormalizeRows(array $headers, array $rows): array
{
    $maxColumns = count($headers);
    foreach ($rows as $row) {
        $maxColumns = max($maxColumns, count($row));
    }

    if ($maxColumns === 0) {
        return [[], []];
    }

    for ($i = count($headers); $i < $maxColumns; $i++) {
        $headers[] = 'Columna ' . ($i + 1);
    }
    $headers = array_map(
        static fn($header, $index) => nfimportCleanHeader($header, $index),
        $headers,
        array_keys($headers)
    );
    $headers = array_slice($headers, 0, $maxColumns);

    $normalizedRows = [];
    foreach ($rows as $row) {
        $row = array_values($row);
        if (count($row) < $maxColumns) {
            $row = array_pad($row, $maxColumns, '');
        } elseif (count($row) > $maxColumns) {
            $row = array_slice($row, 0, $maxColumns);
        }
        $normalizedRows[] = $row;
    }

    return [$headers, $normalizedRows];
}

function nfimportReadRowsFromCsv(string $filename, string $separator, bool $hasHeader): array
{
    $handle = fopen($filename, 'r');
    if ($handle === false) {
        throw new RuntimeException('No se pudo abrir el archivo CSV.');
    }

    $rows = [];
    while (($data = fgetcsv($handle, 0, $separator)) !== false) {
        $rows[] = $data;
    }
    fclose($handle);

    $headers = [];
    if ($hasHeader && count($rows) > 0) {
        $headers = array_shift($rows);
    }

    if (!$hasHeader) {
        $firstRow = $rows[0] ?? [];
        for ($i = 0; $i < count($firstRow); $i++) {
            $headers[] = 'Columna ' . ($i + 1);
        }
    }

    [$headers, $rows] = nfimportNormalizeRows($headers, $rows);
    return ['headers' => $headers, 'rows' => $rows];
}

function nfimportReadRowsFromExcel(string $filename, string $range, bool $hasHeader): array
{
    $spreadsheet = IOFactory::load($filename);
    $worksheet = $spreadsheet->getActiveSheet();

    if ($range !== '') {
        $rows = $worksheet->rangeToArray($range, null, true, true, false);
    } else {
        $rows = $worksheet->toArray(null, true, true, false);
    }

    $headers = [];
    if ($hasHeader && count($rows) > 0) {
        $headers = array_shift($rows);
    }

    if (!$hasHeader) {
        $firstRow = $rows[0] ?? [];
        for ($i = 0; $i < count($firstRow); $i++) {
            $headers[] = 'Columna ' . ($i + 1);
        }
    }

    [$headers, $rows] = nfimportNormalizeRows($headers, $rows);
    return ['headers' => $headers, 'rows' => $rows];
}

function nfimportSuggestExcelRange(string $filename): string
{
    $spreadsheet = IOFactory::load($filename);
    $worksheet = $spreadsheet->getActiveSheet();
    $highestColumn = $worksheet->getHighestDataColumn();
    $highestRow = (int) $worksheet->getHighestDataRow();

    if ($highestRow <= 0 || $highestColumn === '') {
        return 'A1:A1';
    }

    return 'A1:' . $highestColumn . $highestRow;
}

function nfimportReadInputData(array $file, string $importType, string $separator, bool $hasHeader, string $range): array
{
    if (!isset($file['tmp_name']) || $file['tmp_name'] === '') {
        throw new InvalidArgumentException('No se recibió un archivo para importar.');
    }

    $importType = strtolower(trim($importType));
    if ($importType === 'xlsx' || $importType === 'xls') {
        return nfimportReadRowsFromExcel($file['tmp_name'], trim($range), $hasHeader);
    }

    $separator = $separator === '' ? ',' : $separator;
    return nfimportReadRowsFromCsv($file['tmp_name'], $separator, $hasHeader);
}

function nfimportTypeByField(string $fieldType): string
{
    return match (strtolower($fieldType)) {
        'inputnumber' => 'integer',
        'inputdate' => 'date',
        'inputdatetime' => 'datetime',
        'inputcheckbox' => 'boolean',
        default => 'string',
    };
}

function nfimportBuildFieldCatalog($tabla): array
{
    $targetFields = [];
    foreach ($tabla->nffields as $field) {
        $targetFields[] = [
            'field' => $field->field,
            'label' => $field->short_description ?: $field->field,
            'defaultType' => nfimportTypeByField($field->type),
            'matchKeys' => [
                nfimportNormalizeKey((string) $field->field),
                nfimportNormalizeKey((string) $field->short_description),
            ],
        ];
    }
    return $targetFields;
}

function nfimportBuildDefaultMappings(array $headers, array $targetFields): array
{
    $mappings = [];
    foreach ($headers as $index => $header) {
        $normalizedHeader = nfimportNormalizeKey((string) $header);
        $matchedField = null;
        foreach ($targetFields as $targetField) {
            if (in_array($normalizedHeader, $targetField['matchKeys'], true)) {
                $matchedField = $targetField;
                break;
            }
        }

        $mappings[] = [
            'sourceIndex' => $index,
            'sourceName' => $header,
            'enabled' => $matchedField !== null,
            'targetField' => $matchedField['field'] ?? '',
            'type' => $matchedField['defaultType'] ?? 'string',
        ];
    }

    return $mappings;
}

function nfimportParseDate(string $value, array $formats): DateTime
{
    foreach ($formats as $format) {
        $dt = DateTime::createFromFormat($format, $value, new DateTimeZone('UTC'));
        if ($dt !== false) {
            return $dt;
        }
    }

    $timestamp = strtotime($value);
    if ($timestamp !== false) {
        $dt = new DateTime('@' . $timestamp);
        $dt->setTimezone(new DateTimeZone('UTC'));
        return $dt;
    }

    throw new InvalidArgumentException('Fecha inválida: ' . $value);
}

function nfimportConvertValue($value, string $type, int $rowNumber, string $columnName)
{
    if (is_string($value)) {
        $value = trim($value);
    }

    if ($value === '' || $value === null) {
        return null;
    }

    $type = strtolower(trim($type));
    return match ($type) {
        'integer' => is_numeric($value)
            ? (int) $value
            : throw new InvalidArgumentException("Fila {$rowNumber}, columna {$columnName}: se esperaba entero."),
        'float' => is_numeric($value)
            ? (float) $value
            : throw new InvalidArgumentException("Fila {$rowNumber}, columna {$columnName}: se esperaba decimal."),
        'boolean' => match (strtolower((string) $value)) {
            '1', 'true', 'yes', 'si', 'sí' => true,
            '0', 'false', 'no' => false,
            default => throw new InvalidArgumentException("Fila {$rowNumber}, columna {$columnName}: se esperaba booleano."),
        },
        'date' => new UTCDateTime(
            nfimportParseDate((string) $value, ['Y-m-d', 'd/m/Y', 'm/d/Y'])->getTimestamp() * 1000
        ),
        'datetime' => new UTCDateTime(
            nfimportParseDate((string) $value, ['Y-m-d H:i:s', 'Y-m-d\TH:i', 'd/m/Y H:i:s', 'd/m/Y H:i'])->getTimestamp() * 1000
        ),
        default => (string) $value,
    };
}

function nfimportBuildDocuments(array $rows, array $mapping): array
{
    $documents = [];
    $skippedRows = 0;

    foreach ($rows as $rowIndex => $row) {
        $doc = [];
        foreach ($mapping as $map) {
            if (empty($map['enabled']) || empty($map['targetField'])) {
                continue;
            }
            $sourceIndex = (int) $map['sourceIndex'];
            $sourceName = (string) ($map['sourceName'] ?? ('Columna ' . ($sourceIndex + 1)));
            $rawValue = $row[$sourceIndex] ?? null;
            $converted = nfimportConvertValue($rawValue, (string) ($map['type'] ?? 'string'), $rowIndex + 1, $sourceName);
            if ($converted !== null) {
                $doc[$map['targetField']] = $converted;
            }
        }

        if (count($doc) > 0) {
            $documents[] = $doc;
        } else {
            $skippedRows++;
        }
    }

    return ['documents' => $documents, 'skipped_rows' => $skippedRows];
}

if ($nframework->isAjax()) {
    $result = ['status' => 'error', 'message' => 'Operación inválida'];
    try {
        $op = $_POST['op'] ?? 'preview_import';
        $importFileType = $_POST['import_file_type'] ?? 'csv';
        $importFileSeparator = $_POST['import_file_separator'] ?? ',';
        $importFileHasHeader = ($_POST['import_file_has_header'] ?? 'yes') === 'yes';
        $importFileRange = $_POST['import_file_range'] ?? '';
        $file = $_FILES['importfile'] ?? [];

        if ($op === 'suggest_excel_range') {
            $normalizedType = strtolower(trim((string) $importFileType));
            if ($normalizedType !== 'xlsx' && $normalizedType !== 'xls') {
                throw new InvalidArgumentException('El rango sugerido solo aplica para archivos Excel.');
            }
            if (!isset($file['tmp_name']) || $file['tmp_name'] === '') {
                throw new InvalidArgumentException('Selecciona un archivo Excel primero.');
            }
            $result = [
                'status' => 'success',
                'suggested_range' => nfimportSuggestExcelRange($file['tmp_name']),
            ];
        } elseif ($op === 'preview_import') {
            $parsedData = nfimportReadInputData(
                $file,
                (string) $importFileType,
                (string) $importFileSeparator,
                $importFileHasHeader,
                (string) $importFileRange
            );
            $targetFields = nfimportBuildFieldCatalog($tabla);
            $result = [
                'status' => 'success',
                'headers' => $parsedData['headers'],
                'preview_rows' => array_slice($parsedData['rows'], 0, 10),
                'total_rows' => count($parsedData['rows']),
                'target_fields' => array_map(static fn($field) => [
                    'field' => $field['field'],
                    'label' => $field['label'],
                    'defaultType' => $field['defaultType'],
                ], $targetFields),
                'default_mappings' => nfimportBuildDefaultMappings($parsedData['headers'], $targetFields),
                'type_options' => ['string', 'integer', 'float', 'boolean', 'date', 'datetime'],
            ];
        } elseif ($op === 'run_import') {
            $parsedData = nfimportReadInputData(
                $file,
                (string) $importFileType,
                (string) $importFileSeparator,
                $importFileHasHeader,
                (string) $importFileRange
            );
            $mapping = json_decode($_POST['mapping'] ?? '[]', true);
            if (!is_array($mapping) || count($mapping) === 0) {
                throw new InvalidArgumentException('Debe seleccionar al menos una columna a importar.');
            }

            $normalizedMapping = [];
            foreach ($mapping as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $enabled = !empty($item['enabled']);
                $targetField = trim((string) ($item['targetField'] ?? ''));
                if (!$enabled || $targetField === '') {
                    continue;
                }
                $normalizedMapping[] = [
                    'enabled' => true,
                    'sourceIndex' => (int) ($item['sourceIndex'] ?? 0),
                    'sourceName' => (string) ($item['sourceName'] ?? ''),
                    'targetField' => $targetField,
                    'type' => (string) ($item['type'] ?? 'string'),
                ];
            }

            if (count($normalizedMapping) === 0) {
                throw new InvalidArgumentException('Debe seleccionar al menos una columna y campo destino.');
            }

            $build = nfimportBuildDocuments($parsedData['rows'], $normalizedMapping);
            if (count($build['documents']) === 0) {
                throw new InvalidArgumentException('No hay filas con datos válidos para importar.');
            }

            $insertResult = $m->{$config['sitedb']}->{$tabla->nfcollection}->insertMany($build['documents']);
            $result = [
                'status' => 'success',
                'inserted_count' => $insertResult->getInsertedCount(),
                'skipped_rows' => $build['skipped_rows'],
            ];
        } else {
            throw new InvalidArgumentException('Operación no soportada.');
        }
    } catch (Throwable $exception) {
        $result = [
            'status' => 'error',
            'message' => $exception->getMessage(),
        ];
    }
} else {
    $nframework->usecommon = true;

    $importFileType = new select([
        'name' => 'import_file_type',
        'caption' => 'Tipo de archivo',
        'options' => [
            'csv' => 'CSV delimitado',
            'xlsx' => 'Excel',
        ],
        'required' => true,
        'value' => 'csv',
    ]);
    $importFileSeparator = new inputText([
        'name' => 'import_file_separator',
        'caption' => 'Separador CSV',
        'required' => true,
        'value' => ',',
    ]);
    $importFileHasHeader = new select([
        'name' => 'import_file_has_header',
        'caption' => 'El archivo tiene encabezado',
        'options' => [
            'yes' => 'Sí',
            'no' => 'No',
        ],
        'required' => true,
        'value' => 'yes',
    ]);
    $importFileRange = new inputText([
        'name' => 'import_file_range',
        'caption' => 'Rango Excel (opcional, ejemplo: A1:C200)',
        'required' => false,
        'value' => '',
    ]);
?>
    <div class="container p-5">
        <div class="box shadow-large">
            <div class="box-title">Importar a <?= htmlspecialchars((string) $tabla->plural, ENT_QUOTES, 'UTF-8') ?></div>
            <?= secureform('', true, 'nfimport_form') ?>
            <div class="grid">
                <div class="row">
                    <div class="cell">
                        <label class="label-for-input" for="importfile">Archivo para importar</label>
                        <input
                            type="file"
                            id="importfile"
                            name="importfile"
                            class="form-control"
                            accept=".csv,.xlsx,.xls">
                    </div>
                </div>
                <div class="row">
                    <div class="cell-md-4"><?= $importFileType ?></div>
                    <div class="cell-md-4"><?= $importFileHasHeader ?></div>
                    <div class="cell-md-4" id="nfimport-separator-cell"><?= $importFileSeparator ?></div>
                </div>
                <div class="row" id="nfimport-range-row">
                    <div class="cell"><?= $importFileRange ?></div>
                </div>
                <div class="row">
                    <div class="cell-md-2">
                        <button type="button" class="button primary" id="nfimport-preview-btn">Ver preview</button>
                    </div>
                    <div class="cell-md-2">
                        <button type="button" class="button success" id="nfimport-run-btn">Importar</button>
                    </div>
                    <div class="cell-md-8">
                        <div id="nfimport-status" class="fg-dark"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="cell">
                        <h5>Mapeo de columnas</h5>
                        <div id="nfimport-mapping"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="cell">
                        <h5>Preview (primeras 10 filas)</h5>
                        <div id="nfimport-preview"></div>
                    </div>
                </div>
                <div class="row">
                    <div class="cell-md-2 offset-md-10">
                        <a href="/nftables/<?= $tabla->nfcollection ?>" class="button primary w-100"><span class="mif-exit"></span> Cerrar</a>
                    </div>
                </div>
            </div>
            </form>
        </div>
    </div>
    <script>
        (function() {
            let previewState = null;

            const form = document.getElementById('nfimport_form');
            const statusEl = document.getElementById('nfimport-status');
            const mappingEl = document.getElementById('nfimport-mapping');
            const previewEl = document.getElementById('nfimport-preview');
            const fileInput = document.getElementById('importfile');
            const typeInput = document.getElementById('import_file_type');
            const rangeInput = document.getElementById('import_file_range');
            const rangeRow = document.getElementById('nfimport-range-row');
            const separatorCell = document.getElementById('nfimport-separator-cell');

            function escapeHtml(value) {
                return String(value ?? '')
                    .replace(/&/g, '&amp;')
                    .replace(/</g, '&lt;')
                    .replace(/>/g, '&gt;')
                    .replace(/"/g, '&quot;')
                    .replace(/'/g, '&#039;');
            }

            function showStatus(message, isError = false) {
                statusEl.className = isError ? 'fg-red' : 'fg-green';
                statusEl.textContent = message;
            }

            function buildFormData(op, mapping) {
                if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
                    throw new Error('Selecciona un archivo primero.');
                }

                const data = new FormData();
                data.append('op', op);
                data.append('CSRFToken', form.querySelector('input[name="CSRFToken"]').value);
                data.append('importfile', fileInput.files[0]);
                data.append('import_file_type', document.getElementById('import_file_type').value);
                data.append('import_file_separator', document.getElementById('import_file_separator').value);
                data.append('import_file_has_header', document.getElementById('import_file_has_header').value);
                data.append('import_file_range', document.getElementById('import_file_range').value);
                if (mapping) {
                    data.append('mapping', JSON.stringify(mapping));
                }
                return data;
            }

            async function callImport(op, mapping = null) {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    },
                    body: buildFormData(op, mapping),
                });
                return response.json();
            }

            function resetPreviewState() {
                previewState = null;
                mappingEl.innerHTML = '';
                previewEl.innerHTML = '';
            }

            function isExcelTypeSelected() {
                const selectedType = String(typeInput.value || '').toLowerCase();
                return selectedType === 'xlsx' || selectedType === 'xls';
            }

            async function updateExcelRangeSuggestion() {
                if (!isExcelTypeSelected()) {
                    return;
                }
                if (!fileInput.files || fileInput.files.length === 0) {
                    return;
                }
                if (String(rangeInput.value || '').trim() !== '') {
                    return;
                }

                showStatus('Detectando rango sugerido...');
                const data = await callImport('suggest_excel_range');
                if (data.status !== 'success') {
                    throw new Error(data.message || 'No se pudo sugerir el rango Excel.');
                }
                rangeInput.value = data.suggested_range || '';
                showStatus('Rango sugerido: ' + rangeInput.value);
            }

            function updateRangeVisibility() {
                if (isExcelTypeSelected()) {
                    rangeRow.style.display = '';
                    separatorCell.style.display = 'none';
                } else {
                    rangeRow.style.display = 'none';
                    rangeInput.value = '';
                    separatorCell.style.display = '';
                }
            }

            function renderMapping(previewData) {
                const fieldOptions = ['<option value="">-- Omitir --</option>'];
                previewData.target_fields.forEach((field) => {
                    fieldOptions.push('<option value="' + escapeHtml(field.field) + '">' + escapeHtml(field.label) + '</option>');
                });
                const typeOptions = previewData.type_options.map((type) => '<option value="' + escapeHtml(type) + '">' + escapeHtml(type) + '</option>').join('');

                let html = '<table class="table striped"><thead><tr><th>Importar</th><th>Columna origen</th><th>Campo destino</th><th>Tipo</th></tr></thead><tbody>';
                previewData.default_mappings.forEach((map) => {
                    html += '<tr data-source-index="' + map.sourceIndex + '" data-source-name="' + escapeHtml(map.sourceName) + '">' +
                        '<td><input type="checkbox" class="nfimport-enabled" ' + (map.enabled ? 'checked' : '') + '></td>' +
                        '<td>' + escapeHtml(map.sourceName) + '</td>' +
                        '<td><select class="nfimport-target form-control">' + fieldOptions.join('') + '</select></td>' +
                        '<td><select class="nfimport-type form-control">' + typeOptions + '</select></td>' +
                        '</tr>';
                });
                html += '</tbody></table>';

                mappingEl.innerHTML = html;
                const rows = mappingEl.querySelectorAll('tbody tr');
                rows.forEach((row, index) => {
                    const map = previewData.default_mappings[index];
                    row.querySelector('.nfimport-target').value = map.targetField || '';
                    row.querySelector('.nfimport-type').value = map.type || 'string';
                });
            }

            function renderPreview(previewData) {
                let html = '<div class="mb-2">Filas detectadas: <strong>' + escapeHtml(previewData.total_rows) + '</strong></div>';
                html += '<div style="overflow:auto; max-height: 350px;">';
                html += '<table class="table striped"><thead><tr>';
                previewData.headers.forEach((header) => {
                    html += '<th>' + escapeHtml(header) + '</th>';
                });
                html += '</tr></thead><tbody>';

                previewData.preview_rows.forEach((row) => {
                    html += '<tr>';
                    row.forEach((cell) => {
                        html += '<td>' + escapeHtml(cell) + '</td>';
                    });
                    html += '</tr>';
                });

                if (previewData.preview_rows.length === 0) {
                    html += '<tr><td colspan="' + previewData.headers.length + '">Sin filas para mostrar.</td></tr>';
                }

                html += '</tbody></table></div>';
                previewEl.innerHTML = html;
            }

            function collectMapping() {
                const rows = mappingEl.querySelectorAll('tbody tr');
                const mapping = [];
                rows.forEach((row) => {
                    mapping.push({
                        enabled: row.querySelector('.nfimport-enabled').checked,
                        sourceIndex: Number(row.getAttribute('data-source-index')),
                        sourceName: row.getAttribute('data-source-name') || '',
                        targetField: row.querySelector('.nfimport-target').value,
                        type: row.querySelector('.nfimport-type').value,
                    });
                });
                return mapping;
            }

            document.getElementById('nfimport-preview-btn').addEventListener('click', async function() {
                try {
                    showStatus('Generando preview...');
                    const data = await callImport('preview_import');
                    if (data.status !== 'success') {
                        throw new Error(data.message || 'No fue posible generar el preview.');
                    }
                    previewState = data;
                    renderMapping(data);
                    renderPreview(data);
                    showStatus('Preview generado correctamente.');
                } catch (error) {
                    showStatus(error.message, true);
                }
            });

            document.getElementById('nfimport-run-btn').addEventListener('click', async function() {
                try {
                    if (!previewState) {
                        throw new Error('Primero genera el preview.');
                    }
                    const mapping = collectMapping();
                    showStatus('Importando datos...');
                    const data = await callImport('run_import', mapping);
                    if (data.status !== 'success') {
                        throw new Error(data.message || 'No se pudo completar la importación.');
                    }
                    showStatus('Importación completada. Registros insertados: ' + data.inserted_count + '. Filas omitidas: ' + data.skipped_rows + '.');
                } catch (error) {
                    showStatus(error.message, true);
                }
            });

            typeInput.addEventListener('change', async function() {
                try {
                    resetPreviewState();
                    updateRangeVisibility();
                    await updateExcelRangeSuggestion();
                } catch (error) {
                    showStatus(error.message, true);
                }
            });

            fileInput.addEventListener('change', async function() {
                try {
                    resetPreviewState();
                    await updateExcelRangeSuggestion();
                } catch (error) {
                    showStatus(error.message, true);
                }
            });

            updateRangeVisibility();
        })();
    </script>
<?php
}
