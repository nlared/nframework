<?php

class Table
{
    private $ajax;
    public $select;
    public $order;
    public $id;
    public $addclass;
    public $columns;
    public $data = [];
    public $header;
    public $foot;
    public $columnDefs = [];
    public $tableborder;
    public $query = [];
    public $projection = [];
    public $role = 'datastable';
    public $striped = true;
    public $border = true;
    public $rowhover = true;
    public $width = '100%';
    public $responsive = true;
    public $rowborder = false;
    public $cellborder = false;
    public $compact = false;
    public $cellhover = false;
    public $nowrap = false;
    public $rendertargets = true;
    public $scrollX = false;
    // public $lengthMenu='[[5,10, 25, 50, -1], [5,10, 25, 50, "Todos"]]';//TODO: hasta que arreglen en datatables.net
    public $lengthMenu = [[5, 10, 25, 50], [5, 10, 25, 50]];
    public $disablejs = false;
    public $stateSave = 'true';
    public $db;
    public $_id_pos = 0; //position of _id in the columns, used for rowReorder
    public $collection;
    public $pipeline;
    public $footerCallback;
    public $rowReorder = false;

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

    public function __toString()
    {
        global $javas, $javasonce, $nframework;
        if ($this->rowReorder) {
            $nframework->jss['0062'] = 'https://cdn.datatables.net/rowreorder/1.4.1/js/dataTables.rowReorder.min.js';
            $nframework->csss['0062'] = 'https://cdn.datatables.net/rowreorder/1.4.1/css/rowReorder.dataTables.min.css';
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
            $rowReorder = ['selector' => 'tr'];
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
                            data: JSON.stringify({ rows: updates }),
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

    public function positionreset()
    {
        $con = 0;
        $cambios = [];
        $nuevos = [];
        foreach (
            $collection->find([
                'carpeta' => tomongoid($dataset->_id)
            ], [
                'sort' => ['position' => 1]
            ]) as $d
        ) {
            if (!empty($d['position'])) {
                $con++;
                if ($d['position'] != $con) {
                    $cambios[$d['_id']] = $con;
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
