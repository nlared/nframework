<?php
$noobfuscate = true;
require_once 'common.php';

$examples = [
	['title' => 'inputText', 'desc' => 'Opciones comunes a casi todos los controles: <code>name</code>, <code>caption</code>, <code>value</code>, <code>placeholder</code>, <code>required</code>, <code>readonly</code>, <code>disabled</code>, <code>prepend</code>/<code>append</code>. Cualquier otra opción se escribe como atributo HTML.'],
	['code' => "new inputText(['name' => 'texto1', 'caption' => 'Texto simple']);"],
	['code' => "new inputText(['name' => 'texto2', 'caption' => 'Con valor inicial', 'value' => 'Algún texto']);"],
	['code' => "new inputText(['name' => 'texto3', 'caption' => 'Con placeholder', 'placeholder' => 'Escriba aquí...']);"],
	['code' => "new inputText(['name' => 'texto4', 'caption' => 'Prepend y append', 'prepend' => 'https://', 'append' => '.com']);"],
	['code' => "new inputText(['name' => 'texto5', 'caption' => 'Prepend/append con opciones', 'prepend_options' => 'https://,http://', 'append_options' => '.com,.net,.org']);",
		'desc' => 'Lista separada por comas: el usuario elige el prefijo o sufijo.'],
	['code' => "new inputText(['name' => 'texto6', 'caption' => 'Contraseña', 'type' => 'password']);"],
	['code' => "new inputText(['name' => 'texto7', 'caption' => 'Correo', 'type' => 'email', 'placeholder' => 'usuario@dominio.com', 'required' => true]);"],
	['code' => "new inputText(['name' => 'texto8', 'caption' => 'Solo lectura', 'readonly' => true, 'value' => 'No editable']);"],
	['code' => "new inputText(['name' => 'texto9', 'caption' => 'Deshabilitado', 'disabled' => true, 'value' => 'No se envía']);",
		'desc' => 'Un campo <code>disabled</code> no se envía y <code>dataset->save()</code> lo ignora aunque alguien lo agregue al POST.'],
	['code' => "new inputText(['name' => 'texto10', 'caption' => 'backreadonly', 'backreadonly' => true, 'value' => 'Editable pero no se guarda']);",
		'desc' => 'Se puede escribir en él, pero <code>dataset->save()</code> nunca lo guarda.'],
	['code' => "new inputText(['name' => 'texto11', 'caption' => 'Mayúsculas al escribir', 'uppercase' => true]);"],
	['code' => "new inputText(['name' => 'texto12', 'caption' => 'Minúsculas y sin espacios extra', 'lowercase' => true, 'autotrim' => true, 'value' => '  TEXTO MIXTO  ']);"],
	['code' => "new inputText(['name' => 'texto13', 'caption' => 'Máscara de teléfono', 'mask' => '___-___-____', 'mask_pattern' => '\\d']);"],
	['code' => "new inputText(['name' => 'texto14', 'caption' => 'Patrón (regex)', 'pattern' => '^[A-Z]{3}-\\d{4}$', 'placeholder' => 'ABC-1234']);",
		'desc' => 'El patrón se valida en el navegador y también en el servidor al guardar. Ver <a href="validations.php">Validaciones</a>.'],
	['code' => "new inputText(['name' => 'texto15', 'caption' => 'Autocompletar del navegador', 'list' => 'frutas', 'autocomplete' => 'off']) . '<datalist id=\"frutas\"><option>Manzana</option><option>Mango</option><option>Pera</option></datalist>';",
		'desc' => 'Las opciones desconocidas (<code>list</code>) pasan como atributos HTML.'],

	['title' => 'inputNumber e inputSpinner'],
	['code' => "new inputNumber(['name' => 'numero1', 'caption' => 'Número']);"],
	['code' => "new inputNumber(['name' => 'numero2', 'caption' => 'Mínimo, máximo y paso', 'min' => -5, 'max' => 30, 'step' => 0.5, 'value' => 2.5]);"],
	['code' => "new inputNumber(['name' => 'numero3', 'caption' => 'Importe', 'prepend' => '$', 'append' => 'MXN']);"],
	['code' => "new inputSpinner(['name' => 'spinner1', 'caption' => 'Spinner', 'value' => 10, 'validate' => 'integer']);"],

	['title' => 'Textarea y editor HTML'],
	['code' => "new textarea(['name' => 'area1', 'caption' => 'Textarea', 'placeholder' => 'Escriba aquí...']);"],
	['code' => "new textarea(['name' => 'area2', 'caption' => 'Con contador de caracteres', 'charscounter' => 200, 'charscountertemplate' => '\$charsUsed/\$charsTotal']);"],
	['code' => "new inputMCE(['name' => 'mce1', 'id' => 'mce1', 'caption' => 'TinyMCE', 'mediadir' => __DIR__ . '/tmp/', 'baseurl' => '/docs/tmp/', 'upload' => true]);",
		'desc' => 'Editor TinyMCE. Con <code>upload</code> las imágenes pegadas se guardan en <code>mediadir</code> y se sirven desde <code>baseurl</code>.'],

	['title' => 'Casillas y opciones'],
	['code' => "new inputCheckbox(['name' => 'casilla1', 'caption' => 'Acepto los términos']);"],
	['code' => "new inputCheckbox(['name' => 'casilla2', 'caption' => 'Marcada por defecto', 'value' => true]);"],
	['code' => "new inputCheckboxs(['name' => 'casillas1', 'caption' => 'Varias casillas', 'options' => ['1' => 'Opción 1', '2' => 'Opción 2']]);",
		'desc' => 'Se envía como arreglo con las claves marcadas.'],
	['code' => "new inputCheckboxs(['name' => 'casillas2', 'caption' => 'Horizontal con valor', 'horizontal' => true, 'options' => ['a' => 'Alpha', 'b' => 'Beta', 'c' => 'Gamma'], 'value' => ['a' => true, 'c' => true]]);"],
	['code' => "new inputRadios(['name' => 'radio1', 'caption' => 'Radios', 'options' => ['low' => 'Baja', 'medium' => 'Media', 'high' => 'Alta'], 'value' => 'medium']);"],

	['title' => 'Select', 'desc' => 'Para buscar opciones en una colección mientras se escribe vea <a href="inputsajax.php">Select con AJAX</a>.'],
	['code' => "new select(['name' => 'select1', 'caption' => 'Simple', 'options' => ['' => 'Seleccione...', '1' => 'Opción 1', '2' => 'Opción 2']]);"],
	['code' => "new select(['name' => 'select2', 'caption' => 'Agrupado', 'options' => ['Frontend' => ['html' => 'HTML', 'css' => 'CSS'], 'Backend' => ['php' => 'PHP', 'node' => 'Node.js']], 'value' => 'php']);",
		'desc' => 'Un arreglo dentro de <code>options</code> se convierte en <code>&lt;optgroup&gt;</code>.'],
	['code' => "new select(['name' => 'select3', 'caption' => 'Múltiple', 'multiple' => true, 'options' => ['php' => 'PHP', 'js' => 'JavaScript', 'go' => 'Go'], 'value' => ['php', 'js']]);"],
	['code' => "new select(['name' => 'select4', 'caption' => 'Combobox (permite agregar)', 'combobox' => true, 'canadd' => true, 'options' => ['' => 'Seleccione...', 'uno' => 'Uno', 'dos' => 'Dos'], 'value' => 'dos']);"],
	['code' => "new SelectIcon(['name' => 'icono1', 'caption' => 'Con iconos', 'options' => ['home' => ['datashow' => 'Inicio', 'icon' => 'mif-home'], 'user' => ['datashow' => 'Usuario', 'icon' => 'mif-user'], 'settings' => ['datashow' => 'Configuración', 'icon' => 'mif-cog']], 'value' => 'home']);"],

	['title' => 'Color y calificación'],
	['code' => "new inputColor(['name' => 'color1', 'caption' => 'Color', 'value' => '#ff8800']);"],
	['code' => "new inputRating(['name' => 'rating1', 'caption' => 'Calificación', 'value' => 3, 'data-values' => '1,2,3,4,5']);"],

	['title' => 'Ubicación', 'desc' => '<code>inputaddress</code> guarda calle, número, colonia, código postal, ciudad y coordenadas en un solo subdocumento.'],
	['code' => "new inputaddress(['name' => 'domicilio1', 'caption' => 'Domicilio con mapa', 'defaults' => ['country' => 'México', 'state' => 'Coahuila'], 'frozen' => ['country']]);",
		'desc' => '<code>frozen</code> deja fijos los campos indicados.'],
	['code' => "new inputaddress(['name' => 'domicilio2', 'showmap' => false, 'fields' => ['street', 'external_number', 'neighborhood', 'zipcode', 'city']]);"],
	['code' => "new inputaddress(['name' => 'domicilio3', 'showfields' => false, 'defaults' => ['lat' => 25.4232, 'lng' => -101.0053], 'frozen' => ['location'], 'map_height' => 250]);"],
	['code' => "new mapmarker(['name' => 'mapa1', 'caption' => 'Marcar un punto']);"],

	['title' => 'Otros'],
	['code' => "new label(['name' => 'etiqueta1', 'caption' => 'Etiqueta', 'value' => 'Versión 1.0']);",
		'desc' => 'Muestra un valor sin campo editable.'],
	['code' => "'<code>' . htmlspecialchars((string) new inputHidden(['name' => 'oculto1', 'value' => 'token-demo-123'])) . '</code>';",
		'desc' => 'Campo oculto (aquí se muestra su HTML).'],
];
?>
<div class="container">
	<?= docHeader('Inputs', 'Cada control es un objeto que se imprime con <code>echo</code> o <code>&lt;?= ?&gt;</code>. Para leer y guardar sus valores en MongoDB automáticamente use <a href="databinding.php">databinding</a>. Archivos y firmas tienen su propia página: <a href="files.php">Archivos</a>, <a href="signature.php">Firma</a>.') ?>
	<?= docExamples($examples) ?>
</div>
