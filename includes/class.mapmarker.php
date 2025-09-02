<?php

class mapmarker extends baseInput
{
    public const GeoJSON = 0x02;

    //	public $latitude;
    //	public $longitude;
    public $onchange;

    public $type;

    public $height = 500;

    public $value;

    public $startpoint = [
        'lat' => 25.43328030,
        'lng' => -100.96047970,
    ];

    public function __toString()
    {
        global $nframework,$javas;
        $nframework->csss['005rte'] = 'https://unpkg.com/leaflet@1.6.0/dist/leaflet.css" integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ==" crossorigin="';
        $nframework->jss['100leaflet'] = 'https://unpkg.com/leaflet@1.6.0/dist/leaflet.js" integrity="sha512-gZwIG9x3wUXg2hdXF6+rVkLF/0Vi9U8D2Ntg4Ga5I5BZpVkVxlJWbSQtXPSiUTtC0TjtGOmxa1AJPuV0CPthew==" crossorigin="';

        if (! $nframework->onces['maps']) {
            $javas->addjs('var maps=[];');
            $nframework->onces['maps'] = true;
        }
        if (! $nframework->onces['mapsmarker']) {
            $javas->addjs('var mapsmarker=[];');
            $nframework->onces['mapsmarker'] = true;
        }
        $css = '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.6.0/dist/leaflet.css"
	integrity="sha512-xwE/Az9zrjBIphAcBb3F6JVqxf46+CDLwfLMHloNu6KEQCAWi6HcDUbeOfBIptF7tcCzusKFjFw2yuvEpDL9wQ=="
	crossorigin=""/>';

        // $lat=($this->value['lat']!=''?$this->value['lat']:$this->startpoint['lat']);
        // $lng=($this->value['lng']!=''?$this->value['lng']:$this->startpoint['lng']);

        if (empty($this->value)) {
            $lat = $this->startpoint['lat'];
            $lng = $this->startpoint['lng'];
        } else {
            if ($this->type == self::GeoJSON) {
                $lat = $this->value['coordinates']['0'];
                $lng = $this->value['coordinates']['1'];
            } else {
                $lat = $this->value['lat'];
                $lng = $this->value['lng'];
            }
        }

        $javas->addjs("
		var startPoint = [$lat,$lng];
		maps['".$this->id."_map'] = L.map('".$this->id."_map', {editable: true}).setView(startPoint, 16),
    	tilelayer = L.tileLayer('https://{s}.tile.openstreetmap.fr/hot/{z}/{x}/{y}.png', {maxZoom: 20, attribution: 'Data \u00a9 <a href=\"https://www.openstreetmap.org/copyright\"> OpenStreetMap Contributors </a> Tiles \u00a9 HOT'})
		.addTo(maps['".$this->id."_map']);
		
		mapsmarker['".$this->id."_mapmarker'] = new L.marker([$lat,$lng], { draggable:'true'});
    	mapsmarker['".$this->id."_mapmarker'].on('dragend', function(event){
            var marker = event.target;
            var position = marker.getLatLng();
            maps['".$this->id."_map'].flyTo(position);
            $('#".$this->id."_lat').val(position.lat);
            $('#".$this->id."_lng').val(position.lng);
            ".(! empty($this->onchange) ? $this->onchange : '')."
            marker.setLatLng(position,{draggable:'true'}).bindPopup(position).update();
    	});
		maps['".$this->id."_map'].addLayer(mapsmarker['".$this->id."_mapmarker']);
		");

        return '<div id="'.$this->id.'_map" style="height:'.$this->height.'px;"></div>
		<input name="'.$this->name.'[lat]" id="'.$this->id.'_lat" type="text" value="'.$lat.'">
		<input name="'.$this->name.'[lng]" id="'.$this->id.'_lng" type="text" value="'.$lng.'" >
		';
    }

    public function __toMongo($val)
    {
        return $this->type == self::GeoJSON ? ['type' => 'Point', 'coordinates' => [$val['lat'], $val['lng']]] : $val;
    }
}
