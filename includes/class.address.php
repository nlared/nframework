<?


class inputaddress extends baseInput
{
    public $country;
    public $state;
    public $municipality;
    public $city;
    public $zipcode;
    public $neighborhood;
    public $street;
    public $latitude;
    public $longitude;
    public $external_number;
    public $internal_number;
    public $inputtags = [];
    public $map;
    public $precountry = 0;
    public $prestate = 0;
    public $premunicipality = 0;
    public $precity = 0;

    public function __toMongo($val)
    {
        return $val;
    }
    public function __toPHP($val)
    {
        return $val;;
    }
    public function __construct()
    {

        $this->country = new textBox();
        $this->state = new textBox();
        $this->municipality = new textBox();
        $this->city = new textBox();
        $this->zipcode = new textBox();
        $this->neighborhood = new textBox();
        $this->street = new textBox();
        $this->latitude = new textBox();
        $this->longitude = new textBox();
        $this->external_number = new textBox();
        $this->internal_number = new textBox();
        $this->map = new mapmarker();
        $this->map->height = $this->map_height;
        $this->map->id = $this->id . '_map';
        $this->map->name = $this->id . '_map';
        $this->map->value = [
            'lat' => 25.43328030,
            'lng' => -100.96047970
        ];
        $this->map->onchange = 'updateMapMarker()';
    }


    public function __toString()
    {
        global $javas, $nframework, $config;

        $javas->addjs('llamarApiDenueBus();initMap()', 'ready');
        $nframework->jss['200'] = 'https://maps.googleapis.com/maps/api/js?key=' . $config['google-maps-api'] . '&libraries=places';

        $javas->addjs(
            <<<js
var geocoder;
	function initMap() {
		geocoder = new google.maps.Geocoder();
	}
	

    function updateMapMarker(){
        var mapMarker = mapsmarker['{$this->map->id}'];
        var position = mapMarker.getLatLng();
        document.getElementById('{$this->latitude->id}').value = position.lat;
        document.getElementById('{$this->longitude->id}').value = position.lng;
    }
    document.addEventListener('DOMContentLoaded', function() {
        var mapMarker = mapsmarker['{$this->map->id}'];
        mapMarker.on('dragend', function(e) {
            updateMapMarker();
        });
        mapMarker.addTo(maps['{$this->map->id}']);
    });
    const dialogLoading = document.createElement('dialog'); 
    dialogLoading.innerHTML = '<div class="dialog-title">Cargando...</div><div class="dialog-content"><div class="progress-bar indeterminate"></div></div>';
    document.body.appendChild(dialogLoading);    
    const geocoder = new google.maps.Geocoder();
    
    function buscard(){
        var lat=$("#data_mapa_lat").val();
        var lng=$("#data_mapa_lng").val();
        
        const latlng = {
        lat: parseFloat(lat),
        lng: parseFloat(lng),
    };
    dialogLoading.showModal();
    geocoder
        .geocode({ location: latlng })
        .then((response) => {
        if (response.results[0]) {
            response.results[0].address_components.forEach((element) => {
                element.types.forEach((type)=>{
                    let tipos = { route: "data_vialidad", sublocality: 'data_asentamiento' ,locality:'data_localidad',administrative_area_level_1:'data_estado',postal_code:'data_cp'};
                    console.log(type);
                    console.log(element);
                    if (Object.hasOwn(tipos, type)){
                        console.log(type+' '+element.long_name);
                        $('#'+tipos[type]).val(element.long_name);
                    }
                },element);
            });
        } else {
            console.log("No results found");
        }
        dialogLoading.close();
    })
    .catch((e) => {console.log("Geocoder failed due to: " + e); dialogLoading.close();});
    }
    document.getElementById('txttomap').addEventListener('click', function() {
        var address = '';
        address += document.getElementById('{$this->street->id}').value + ', ';
        address += document.getElementById('{$this->external_number->id}').value + ', ';
        address += document.getElementById('{$this->neighborhood->id}').value + ', ';
        address += document.getElementById('{$this->city->id}').value + ', ';
        address += document.getElementById('{$this->state->id}').value + ', ';
        address += document.getElementById('{$this->country->id}').value + ', ';
        address += document.getElementById('{$this->zipcode->id}').value;
        
        dialogLoading.showModal();
        geocoder
            .geocode({ address: address })
            .then((response) => {
            if (response.results[0]) {
                const position = response.results[0].geometry.location;
                const map = maps['{$this->map->id}'];
                map.setView([position.lat(), position.lng()], 16);
                const mapMarker = mapsmarker['{$this->map->id}'];
                mapMarker.setLatLng([position.lat(), position.lng()]);
                updateMapMarker();
            } else {
                console.log("No results found");
            }
            dialogLoading.close();
        })
        .catch((e) => {console.log("Geocoder failed due to: " + e); dialogLoading.close();});
    }
        
js
        );


        return <<<html
         <div class="grid">
			<div class="row">
				<div class="cell">
					<div class="row">
                        <div class="cell-md-6"><?= $this->country ?></div>
						<div class="cell-md-6"><?= $this->zipcode ?></div>
					</div>
					<div class="row">
						<div class="cell-md-6"><?= $this->state ?></div>
						<div class="cell-md-6"><?= $this->municipality ?></div>
					</div>
					<div class="row">
						<div class="cell-md-6"><?= $this->city ?></div>
						<div class="cell-md-6"><?= $this->neighborhood ?></div>
					</div>
					
					<div class="row">
						<div class="cell-md-6"><?= $this->street ?></div>
						<div class="cell-md-3"><?= $this->external_number ?></div>
						<div class="cell-md-3"><?= $this->internal_number ?></div>
					</div>
					<div class="row">
						<div class="cell"><button class="button" id="txttomap">Buscar direccion en el mapa</button></div>						
					</div>
				</div>
				<div class="cell"><?=$this->map?></div>
			</div>
		</div>
html;
    }
}
