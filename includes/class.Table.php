<?php

/**
 * Tabla DataTables. Con datos de PHP: llene $data con filas de celdas HTML (escape con htmlspecialchars()).
 * Paginada en el servidor: Ajax() guarda la consulta en la sesión y /nframework/datatable.php pagina, busca y
 * ordena en MongoDB. En el navegador: datatables['<id>'].
 *
 *   $t = new Table(['header' => '<th>Nombre</th><th>Total</th>', 'order' => [[1, 'desc']]]);
 *   $t->data[] = [htmlspecialchars($nombre), number_format($total, 2)];
 *   echo $t;
 *
 *   $t = new Table();
 *   $t->Ajax(['id' => 'tabla1', 'db' => $config['sitedb'], 'collection' => 'productos',
 *       'header' => '<th>Nombre</th><th></th>', 'columns' => ['nombre', '_id'],
 *       'pipeline' => [['$match' => ['owner' => $user->_id]]]]);
 *   echo $t;
 */
class Table
{
    /** @var bool|null true después de Ajax(). */
    private $ajax;
    /** @var mixed Opción select de DataTables (JSON o valor). */
    public $select;
    /** @var array<mixed>|string|null Orden inicial: [[columna, 'asc'|'desc'], ...]. */
    public $order;
    /** @var string Id de la tabla; por omisión DataTables_Table_<n>. En JS: datatables[id]. */
    public $id;
    /** @var string|null Clases CSS extra de la <table>. */
    public $addclass;
    /** @var list<string>|null Campos del documento por columna (modo Ajax), en notación con punto. */
    public $columns;
    /** @var list<list<string>> Filas de celdas HTML (modo PHP). */
    public $data = [];
    /** @var string|null HTML de los <th> del encabezado. */
    public $header;
    /** @var string|null HTML de los <th> del pie. */
    public $foot;
    /** @var array<int|string, array<string, mixed>> Por índice de columna: ['render' => 'expresión JS con data, type, row'] y otras opciones de DataTables. */
    public $columnDefs = [];
    /** @var mixed */
    public $tableborder;
    /** @var array<string, mixed> Filtro para find() (modo Ajax sin pipeline). */
    public $query = [];
    /** @var array<string, mixed> */
    public $projection = [];
    /** @var string */
    public $role = 'datastable';
    /** @var bool */
    public $striped = true;
    /** @var bool */
    public $border = true;
    /** @var bool */
    public $rowhover = true;
    /** @var string */
    public $width = '100%';
    /** @var bool */
    public $responsive = true;
    /** @var bool */
    public $rowborder = false;
    /** @var bool */
    public $cellborder = false;
    /** @var bool */
    public $compact = false;
    /** @var bool */
    public $cellhover = false;
    /** @var bool */
    public $nowrap = false;
    /** @var bool */
    public $rendertargets = true;
    /** @var bool */
    public $scrollX = false;
    // public $lengthMenu='[[5,10, 25, 50, -1], [5,10, 25, 50, "Todos"]]';//TODO: hasta que arreglen en datatables.net
    /** @var array{0: list<int>, 1: list<int|string>} [valores, etiquetas] del selector de filas por página. */
    public $lengthMenu = [[5, 10, 25, 50], [5, 10, 25, 50]];
    /** @var bool true = no inicializa DataTables (solo imprime la tabla). */
    public $disablejs = false;
    /** @var string 'true'|'false' (texto JS): recordar paginación y orden. */
    public $stateSave = 'true';
    /** @var string|null Base de datos (modo Ajax); normalmente $config['sitedb']. */
    public $db;
    /** @var int */
    public $_id_pos = 0; //position of _id in the columns, used for rowReorder
    /** @var string|null Colección (modo Ajax). */
    public $collection;
    /** @var list<array<string, mixed>>|null Pipeline de aggregate que se aplica antes de paginar (modo Ajax). */
    public $pipeline;
    /** @var string|null Función JS footerCallback de DataTables. */
    public $footerCallback;
    /** @var bool|string Reordenar filas arrastrando (campo position); texto = opciones JSON adicionales. */
    public $rowReorder = false;
    /** @var string */
    public $selectStyle = 'single';
    /** @var bool */
    public $ordering = true;
    /** @var bool Resaltar el texto buscado (mark.js). */
    public $mark = false;

    /**
     * @global class_nframework $nframework
     * @param array<string, mixed> $options Cualquier propiedad pública (header, order, columnDefs, data, id...).
     */
    public function __construct($options = [])
    {
        global $nframework;

        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        $nframework->addjqueryui();
        $nframework->csss['006'] = 'https://cdn.datatables.net/v/dt/dt-1.13.8/r-2.5.0/sc-2.2.0/sl-1.7.0/datatables.min.css';
        $nframework->jss['006'] = 'https://cdn.datatables.net/v/dt/dt-1.13.8/r-2.5.0/sc-2.2.0/sl-1.7.0/datatables.min.js';
        // $nframework->jss['0061']='https://cdn.nlared.com/nframework/4.5.1/dtpipeline.js';
        $nframework->jss['0061'] = 'https://cdn.nlared.com/nframework/4.5.1/dtpipeline.js?dev=' . date('ymdhis');


        // $nframework->jss['002']='https://cdn.datatables.net/1.10.22/js/jquery.dataTables.min.js';
        // $nframework->jss['060']='https://cdn.datatables.net/responsive/2.2.6/js/dataTables.responsive.min.js';
        // $nframework->csss['060']='https://cdn.datatables.net/v/dt/dt-1.10.23/b-1.6.5/b-colvis-1.6.5/b-html5-1.6.5/b-print-1.6.5/r-2.2.7/sc-2.0.3/datatables.min.css';
        // $nframework->jss['060']='https://cdn.datatables.net/v/dt/dt-1.10.23/b-1.6.5/b-colvis-1.6.5/b-html5-1.6.5/b-print-1.6.5/r-2.2.7/sc-2.0.3/datatables.min.js';

        //
        if (empty($this->id)) {
            $this->id = 'DataTables_Table_' . $nframework->counters('table');
        }
    }

    /**
     * Pasa la tabla a modo servidor: guarda db, collection, query, projection, columns y pipeline en
     * $_SESSION['datatable'][id] (una hora) para /nframework/datatable.php. El navegador no puede cambiar la
     * consulta, solo buscar, ordenar y paginar. JS: datatables[id].clearPipeline(); datatables[id].draw();
     *
     * @global class_nframework $nframework
     * @param array{id?: string, db?: string, collection?: string, header?: string, columns?: list<string>, pipeline?: list<array<string, mixed>>, query?: array<string, mixed>, projection?: array<string, mixed>, columnDefs?: array<int|string, array<string, mixed>>, order?: array<mixed>} $options Además cualquier otra propiedad pública.
     * @return void
     */
    public function Ajax($options = [])
    {
        global $nframework;
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        $_SESSION['datatable'][$this->id] = [
            'db' => $this->db,
            'collection' => $this->collection,
            'query' => $this->query,
            'projection' => $this->projection,
            'columns' => $this->columns,
            'pipeline' => $this->pipeline,
        ];
        addVarToGarbage('datatable\\' . $this->id, time() + (60 * 60));
        $this->ajax = true;
    }
    /**
     * Como Ajax() pero sin activar el modo AJAX: solo registra la consulta en la sesión (lo usan las tablas
     * administrables de /nftables/).
     *
     * @global class_nframework $nframework
     * @param array<string, mixed> $options
     * @return void
     */
    public function nftable($options = [])
    {
        global $nframework;
        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        $_SESSION['datatable'][$this->id] = [
            'db' => $this->db,
            'collection' => $this->collection,
            'query' => $this->query,
            'projection' => $this->projection,
            'columns' => $this->columns,
            'pipeline' => $this->pipeline,
        ];
        addVarToGarbage('datatable\\' . $this->id, time() + (60 * 60));
    }

    /**
     * @global Javas $javas
     * @global array<string, mixed> $javasonce
     * @global class_nframework $nframework
     * @return string HTML de la tabla; registra en $javas su inicialización.
     */
    public function __toString()
    {
        global $javas, $javasonce, $nframework;
        if ($this->rowReorder) {
            $nframework->jss['0062'] = 'https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js';
            $nframework->csss['0062'] = 'https://cdn.datatables.net/rowreorder/1.4.1/css/rowReorder.dataTables.min.css';
        }
        if ($this->mark) {
            $nframework->jss['0063'] = 'https://cdn.jsdelivr.net/npm/mark.js@8.11.1/dist/jquery.mark.min.js';
            $nframework->jss['0064'] = 'https://cdn.datatables.net/plug-ins/1.13.8/features/mark.js/datatables.mark.min.js';
        }

        $class = [];
        if ($this->border) $class[] = 'table-border';
        if ($this->rowborder) $class[] = 'row-border';
        if ($this->cellborder) $class[] = 'cell-border';
        if ($this->compact) $class[] = 'compact';
        if ($this->rowhover) $class[] = 'rowhover';
        if ($this->cellhover) $class[] = 'cell-hover';
        if ($this->striped) $class[] = 'striped';
        if (!empty($this->addclass)) $class[] = $this->addclass;

        $columnDefss = [];
        $result = '<table id="' . $this->id . '" class="' . implode(' ', $class) . ' display' .
            ($this->responsive ? ' responsive' : '') .
            ($this->nowrap ? ' nowrap' : '') .
            '" data-role="' . $this->role . '"' .
            ($this->width ? ' width="' . $this->width . '"' : '') . ' data-searching="true">' .
            ($this->header ? '<thead>' . str_replace('td>', 'th>', $this->header) . '</thead>' : '') .
            ($this->foot ? '<tfoot>' . $this->foot . '</tfoot>' : '');

        $columnDefs = '';
        if (!empty($this->columnDefs)) {
            if ($this->rendertargets) {
                foreach ($this->columnDefs as $targets => $target) {
                    $columnDefss[] = '{"targets":' . $targets . ',"render":function(data,type,row,meta){return ' . $target['render'] . ';}}';
                }
                $columnDefs = '[' . implode(',', $columnDefss) . ']';
            } else {
                $columnDefs = json_encode($this->columnDefs);
            }
        }



        $json = [
            'language' => $nframework->languages[$nframework->lang]['datatables'],
            'destroy' => true,
            'scrollX' => $this->scrollX,
            'responsive' => $this->responsive,
            'lengthMenu' => $this->lengthMenu,
            'stateSave' => $this->stateSave,
            'mark' => $this->mark,
            // 'order' => !empty($this->order) ? $this->order : [],
            // 'ordering' => !empty($this->ordering) ? true : false,
        ];

        if (!empty($this->columnDefs)) {
            $json['columnDefs'] = 'columnDefsssssss';
        }

        foreach (['footerCallback', 'select', 'order'] as $prop) {
            if (!empty($this->{$prop})) {
                $json[$prop] = $this->{$prop};
            }
        }

        if ($this->ajax) {
            $json['processing'] = true;
            $json['serverSide'] = true;
            $json['ajax'] = 'ajaxconfig';
        } else {
            $result .= '<tbody>';
            foreach ($this->data as $row) {
                $result .= '<tr><td>' . implode('</td><td>', $row) . '</td></tr>';
            }
            $result .= '</tbody>';
        }

        if ($this->rowReorder) {
            $rowReorder = ['selector' => 'button.reorder-handle'];
            if (is_string($this->rowReorder)) {
                $rowReorder = array_merge($rowReorder, json_decode($this->rowReorder, true));
            }
            $json['rowReorder'] = $rowReorder;
        }
        if (!$this->disablejs) {
            $javas->addjs(
                'datatables["' . $this->id . '"]=$("#' . $this->id . '").DataTable(' .
                    str_replace([
                        '"ajaxconfig"',
                        '"columnDefsssssss"',
                    ], [
                        '$.fn.dataTable.pipeline({url: \'/nframework/datatable.php?id=' . $this->id . '\',pages:5})',
                        $columnDefs,
                    ], json_encode($json)) . ');',

                'initializecomponent'
            );
        }


        if ($this->rowReorder) {
            $javas->addjs(
                'datatables["' . $this->id . '"].on( "row-reorder", function ( e, diff, edit ) {
                    let updates = [];                    
                    diff.forEach(function (item) {
                        updates.push({
                            id: datatables["' . $this->id . '"].row(item.node).data()[' . $this->_id_pos . '],   // FIRST COLUMN MUST BE ID
                            newPosition: item.newData
                        });
                    });

                    if (updates.length > 0) {
                        $.ajax({
                            url: "/nframework/datatabler.php?id=' . $this->id . '",
                            method: "POST",
                            contentType: "application/json",
                            data:  JSON.stringify({rows: updates}),
                            success: function(res){
                                console.log("Saved order:", res);
                            },
                            error: function(err){
                                console.error("ERROR:", err.responseText);
                            }
                        });
                    }

                });',
                'initializecomponent'
            );
        }
        return $result . '</table>';
    }

    /**
     * Renumera el campo position (1, 2, 3...) de los documentos del pipeline, en su orden actual; los que no tienen
     * posición van al final. Para tablas con rowReorder.
     *
     * @global MongoDB\Client $m
     * @return void
     */
    public function positionsreset()
    {
        global $m;
        $con = 0;
        $cambios = [];
        $nuevos = [];
        $collection = $m->{$this->db}->{$this->collection};
        $pileline = $this->pipeline;
        $pileline[] = ['$project' => ['_id' => 1, 'position' => 1]];
        $pileline[] = ['$sort' => ['position' => 1]];
        foreach ($collection->aggregate($pileline) as $d) {
            if (!empty($d['position'])) {
                $con++;
                if ($d['position'] != $con) {
                    $cambios[(string)$d['_id']] = $con;
                }
            } else {
                $nuevos[] = $d['_id'];
            }
        }

        foreach ($cambios as $id => $newPosition) {
            $operations[] = [
                'updateOne' => [
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$set' => ['position' => $newPosition]]
                ]
            ];
        }
        foreach ($nuevos as $id) {
            $con++;
            $operations[] = [
                'updateOne' => [
                    ['_id' => new \MongoDB\BSON\ObjectId($id)],
                    ['$set' => ['position' => $con]]
                ]
            ];
        }

        if (!empty($operations)) {
            $collection->bulkWrite($operations);
        }
    }
}
