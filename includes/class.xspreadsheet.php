<?
class xspreadsheet
{
    public $id;
    public $filename;
    public function __construct($options = [])
    {
        global $nframework;

        foreach ($options as $option => $value) {
            $this->{$option} = $value;
        }
        if (empty($this->id)) {
            $this->id = 'xspreadsheet_' . sha1($_SERVER['PHP_SELF']) . '_' . $nframework->counters('xspreadsheet');
        }
        $nframework->csss['900'] = 'https://cdn.jsdelivr.net/npm/x-data-spreadsheet@1.1.9/dist/xspreadsheet.min.css';
        $nframework->jss['900'] = 'https://cdn.jsdelivr.net/npm/x-data-spreadsheet@1.1.9/dist/xspreadsheet.min.js';
        //	$nframework->jss['901']=' https://unpkg.com/x-data-spreadsheet@1.1.5/dist/locale/zh-cn.js';
    }
    public function __toString()
    {
        global $javas;
        $_SESSION['nfxspreadsheet'][$this->id] = [
            'filename' => $this->filename
        ];

        $javas->addjs(
            <<<addjs
        	
        	let rows={};
        	$.ajax({
			    url: '/nframework/xspreadsheet.php?mid={$this->id}',
			    type: 'GET',
			    cache: false,
			    dataType: 'json',
			    success: function(response) {
			    	let rowc=0;
			        
			        response.data.forEach(row => {
			        	console.log(row);
			        	let cells={};
			        	cellc=0;
			        	row.forEach(cell=>{
		        			console.log(cell);
				        	let jsonObject = {
							  text: cell
							};
			        		cells[cellc]=jsonObject;
			        		cellc++;
			        	});
		        		let jsonObject = {
						  cells: cells
						};
			        	
			        	rows[rowc]=jsonObject;
			        	rowc++;
			        });
			        console.log(rows);
			    },
			    error: function(xhr, status, error) {
			        console.error(error);
			    }
			});
        const x_spreadsheet1 = new x_spreadsheet('#{$this->id}')
        .loadData({
			rows: rows
        	
        }) // load data
		.change(data => {
			console.log(data);
			$.ajax({
			    url: '/nframework/xspreadsheet.php?mid={$this->id}',
			    type: 'post',
			    cache: false,
			    data: JSON.stringify(data),
			    dataType: 'json',
			    success: function(response) {
			        console.log(response);
			    },
			    error: function(xhr, status, error) {
			        console.error(error);
			    }
			});
		    
		});
		// data validation
		x_spreadsheet1.validate()
        ;
       // x_spreadsheet1.locale('zh-cn');
addjs
        );
        return '<div id="' . $this->id . '"><div>';
    }
}
