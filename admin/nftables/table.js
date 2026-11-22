/* Editor de campos de Admin → nftables (lo incluye table.php; NFT trae los campos y el catálogo de tipos). */
(function () {
	var fields = NFT.fields || [];
	var editing = -1;          // posición del campo en edición; -1 = nuevo
	var pathsCache = {};
	var dialog = document.getElementById('nft_dialog');
	var LABELS = {
		placeholder: 'Texto dentro del campo vacío', pattern: 'Patrón (expresión regular)', validate: 'Validaciones de Metro (email, minlength=3...)',
		uppercase: 'Convertir a mayúsculas', lowercase: 'Convertir a minúsculas', prepend: 'Texto antes', append: 'Texto después',
		'default': 'Valor por omisión', readonly: 'Solo lectura', charscounter: 'Contador de caracteres', min: 'Mínimo', max: 'Máximo',
		step: 'Incremento (0.01 = decimales)', multiple: 'Permitir varios valores', canadd: 'Permitir agregar valores nuevos',
		horizontal: 'Horizontal', accept: 'Tipos aceptados (image/*, .pdf)', mode: 'Estilo', countlimit: 'Máximo de archivos',
		sizelimit: 'Tamaño máximo (bytes)', width: 'Ancho (px)', height: 'Alto (px)', shape: 'Figura'
	};

	function $id(id) { return document.getElementById(id); }
	function el(tag, attrs, children) {
		var e = document.createElement(tag);
		Object.keys(attrs || {}).forEach(function (k) {
			if (k === 'text') { e.textContent = attrs[k]; }
			else if (k === 'className') { e.className = attrs[k]; }
			else if (k.indexOf('on') === 0) { e.addEventListener(k.slice(2), attrs[k]); }
			else if (attrs[k] === true) { e.setAttribute(k, ''); }
			else if (attrs[k] !== false && attrs[k] !== null && attrs[k] !== undefined) { e.setAttribute(k, attrs[k]); }
		});
		(children || []).forEach(function (c) { e.appendChild(typeof c === 'string' ? document.createTextNode(c) : c); });
		return e;
	}
	function show(id, visible) { $id(id).hidden = !visible; $id(id).style.display = visible ? '' : 'none'; }
	function typeLabel(t) { return NFT.types[t] ? NFT.types[t].label : t; }
	function itemsToText(items) {
		return (items || []).map(function (i) { return i.value === i.label ? i.value : i.value + '=' + i.label; }).join('\n');
	}
	function post(data) {
		data.CSRFToken = NFT.token;
		return $.ajax({ url: location.href, method: 'POST', data: data, dataType: 'json' });
	}
	function errorsBox(id, list) {
		var box = $id(id);
		box.textContent = '';
		(list || []).forEach(function (m) { box.appendChild(el('div', { text: m })); });
		show(id, list && list.length > 0);
	}
	function fail(xhr) { errorsBox('nft_dialog_errors', ['Error de comunicación (' + (xhr && xhr.status) + ').']); }
	function collectionName() { return ($id('nft_collection').value || '').trim(); }
	function sync() { $id('nft_fields').value = JSON.stringify(fields); }

	// ---------------------------------------------------------------- lista de campos
	function move(i, d) {
		var j = i + d;
		if (j < 0 || j >= fields.length) { return; }
		var t = fields[i]; fields[i] = fields[j]; fields[j] = t;
		render();
	}
	function render() {
		var box = $id('nft_list');
		box.textContent = '';
		if (!fields.length) {
			box.appendChild(el('div', { className: 'text-muted p-2', text: 'Sin campos. Agréguelos o use «Detectar campos» para proponerlos a partir de los documentos.' }));
			sync();
			return;
		}
		var body = el('tbody');
		fields.forEach(function (f, i) {
			var extra = f.ajax ? ' · ' + f.ajax.collection : (f.subfields ? ' · ' + f.subfields.length + ' subcampos' : (f.suggest ? ' · autocompletar' : ''));
			body.appendChild(el('tr', {}, [
				el('td', {}, [el('code', { text: f.field })]),
				el('td', { text: f.short_description }),
				el('td', {}, [el('span', { className: 'nft-type', text: typeLabel(f.type) + extra })]),
				el('td', { text: (f.required ? 'Req. ' : '') + (f.list ? '✓ col.' : '') }),
				el('td', { className: 'text-right', style: 'white-space:nowrap' }, [
					el('button', { type: 'button', className: 'button square small', title: 'Subir', onclick: function () { move(i, -1); } }, [el('span', { className: 'mif-arrow-up' })]),
					el('button', { type: 'button', className: 'button square small', title: 'Bajar', onclick: function () { move(i, 1); } }, [el('span', { className: 'mif-arrow-down' })]),
					el('button', { type: 'button', className: 'button square small primary', title: 'Editar', onclick: function () { open(i); } }, [el('span', { className: 'mif-pencil' })]),
					el('button', { type: 'button', className: 'button square small alert', title: 'Quitar', onclick: function () {
						Swal.fire({ title: '¿Quitar el campo ' + f.field + '?', text: 'Los datos guardados no se borran.', icon: 'question', showCancelButton: true, confirmButtonText: 'Quitar', cancelButtonText: 'Cancelar' })
							.then(function (r) { if (r.isConfirmed) { fields.splice(i, 1); render(); } });
					} }, [el('span', { className: 'mif-cross' })])
				])
			]));
		});
		box.appendChild(el('table', { className: 'table compact striped' }, [
			el('thead', {}, [el('tr', {}, ['Campo', 'Etiqueta', 'Tipo', '', ''].map(function (t) { return el('th', { text: t }); }))]),
			body
		]));
		sync();
	}

	// ---------------------------------------------------------------- rutas de campos para sugerir nombres
	function loadPaths(collection, datalistId) {
		var list = $id(datalistId);
		function fill(paths) {
			list.textContent = '';
			(paths || []).forEach(function (p) { list.appendChild(el('option', { value: p })); });
		}
		if (!collection) { fill([]); return; }
		if (pathsCache[collection]) { fill(pathsCache[collection]); return; }
		post({ op: 'campos', collection: collection }).done(function (r) {
			pathsCache[collection] = (r && r.paths) || [];
			fill(pathsCache[collection]);
		});
	}

	// ---------------------------------------------------------------- diálogo de un campo
	function typeSelect(select, groups, value) {
		select.textContent = '';
		Object.keys(groups).forEach(function (g) {
			var og = el('optgroup', { label: g });
			Object.keys(groups[g]).forEach(function (t) { og.appendChild(el('option', { value: t, text: groups[g][t] })); });
			select.appendChild(og);
		});
		select.value = value || 'inputText';
	}
	function renderOptions(type, values) {
		var body = $id('nft_options_body');
		body.textContent = '';
		var opts = (NFT.types[type] || {}).options || {};
		var names = Object.keys(opts);
		names.forEach(function (name) {
			var kind = opts[name], id = 'nft_o_' + name, v = values && values[name] !== undefined ? values[name] : '', input;
			if (kind === 'bool') {
				body.appendChild(el('div', { className: 'cell-md-6' }, [el('label', {}, [el('input', { type: 'checkbox', id: id, 'data-opt': name, checked: !!v }), ' ' + (LABELS[name] || name)])]));
				return;
			}
			if (Array.isArray(kind)) {
				input = el('select', { id: id, 'data-opt': name }, [el('option', { value: '', text: '(por omisión)' })].concat(kind.map(function (k) { return el('option', { value: k, text: k }); })));
				input.value = v;
			} else {
				input = el('input', { type: kind === 'string' ? 'text' : 'number', step: kind === 'int' ? '1' : 'any', id: id, 'data-opt': name, value: v });
			}
			body.appendChild(el('div', { className: 'cell-md-6' }, [el('label', { 'for': id, text: LABELS[name] || name }), input]));
		});
		if (NFT.types[type] && NFT.types[type].files && !NFT.files) {
			body.appendChild(el('div', { className: 'cell-12 remark warning', text: 'Para guardar archivos configure nftables_files (carpeta fuera del document root) en includes/config.php.' }));
		}
		show('nft_options', names.length > 0 || (NFT.types[type] && NFT.types[type].files && !NFT.files));
	}
	function sourceMode() {
		var r = document.querySelector('input[name="nft_src"]:checked');
		return r ? r.value : 'items';
	}
	function updatePanels() {
		var type = $id('nft_f_type').value, def = NFT.types[type] || {}, src = def.source || '';
		show('nft_source', src === 'items' || src === 'items+ajax');
		show('nft_source_mode', src === 'items+ajax');
		var ajax = src === 'items+ajax' && sourceMode() === 'ajax';
		show('nft_items_box', !ajax);
		show('nft_ajax_box', ajax);
		show('nft_suggest', src === 'suggest');
		var mode = $id('nft_s_mode').value;
		show('nft_s_list_box', mode === 'list');
		show('nft_s_coll_box', mode === 'collection');
		show('nft_sub', type === 'embededArray');
		show('nft_try', src !== '' && (src !== 'suggest' || mode !== ''));
		show('nft_code', true);
	}
	function subRow(s) {
		s = s || { field: '', type: 'inputText', short_description: '', required: false, list: true };
		var type = el('select', { className: 'nft-s-type' });
		typeSelect(type, NFT.subgroups, s.type);
		var items = el('textarea', { className: 'nft-s-items', rows: '2', placeholder: 'a=Activo' });
		items.value = itemsToText(s.items);
		function toggle() { var src = (NFT.types[type.value] || {}).source || ''; items.style.display = src.indexOf('items') === 0 ? '' : 'none'; }
		type.addEventListener('change', toggle);
		var row = el('tr', {}, [
			el('td', {}, [el('input', { type: 'text', className: 'nft-s-field', value: s.field, placeholder: 'numero' })]),
			el('td', {}, [type]),
			el('td', {}, [el('input', { type: 'text', className: 'nft-s-label', value: s.short_description })]),
			el('td', {}, [el('input', { type: 'checkbox', className: 'nft-s-required', checked: !!s.required })]),
			el('td', {}, [el('input', { type: 'checkbox', className: 'nft-s-list', checked: s.list !== false })]),
			el('td', {}, [items]),
			el('td', {}, [el('button', { type: 'button', className: 'button square small alert', onclick: function () { row.remove(); } }, [el('span', { className: 'mif-cross' })])])
		]);
		toggle();
		return row;
	}
	function open(i) {
		editing = i;
		var f = i >= 0 ? fields[i] : { field: '', type: 'inputText', short_description: '', long_description: '', required: false, list: true, options: {} };
		$id('nft_dialog_title').textContent = i >= 0 ? 'Campo ' + f.field : 'Campo nuevo';
		$id('nft_f_field').value = f.field || '';
		typeSelect($id('nft_f_type'), NFT.groups, f.type);
		$id('nft_f_label').value = f.short_description || '';
		$id('nft_f_help').value = f.long_description || '';
		$id('nft_f_required').checked = !!f.required;
		$id('nft_f_list').checked = f.list !== false;
		renderOptions(f.type, f.options);
		$id('nft_items').value = itemsToText(f.items);
		var a = f.ajax || {};
		document.querySelector('input[name="nft_src"][value="' + (f.ajax ? 'ajax' : 'items') + '"]').checked = true;
		$id('nft_a_collection').value = a.collection || '';
		$id('nft_a_label').value = (a.label || []).join(', ');
		$id('nft_a_value').value = a.value || '';
		$id('nft_a_columns').value = (a.columns || []).join(', ');
		$id('nft_a_match').value = a.match || '';
		$id('nft_a_limit').value = a.limit || 20;
		var s = f.suggest || {};
		$id('nft_s_mode').value = s.mode || '';
		$id('nft_s_items').value = (s.items || []).join('\n');
		$id('nft_s_collection').value = s.collection || '';
		$id('nft_s_column').value = s.column || '';
		var sub = $id('nft_sub_body');
		sub.textContent = '';
		(f.subfields || []).forEach(function (sf) { sub.appendChild(subRow(sf)); });
		show('nft_test', false);
		errorsBox('nft_dialog_errors', []);
		updatePanels();
		loadPaths(collectionName(), 'nft_paths');
		loadPaths(a.collection, 'nft_a_paths');
		loadPaths(s.collection, 'nft_s_paths');
		dialog.showModal();
	}
	function readDialog() {
		var type = $id('nft_f_type').value, def = NFT.types[type] || {}, f = {
			field: $id('nft_f_field').value.trim(),
			type: type,
			short_description: $id('nft_f_label').value.trim(),
			long_description: $id('nft_f_help').value.trim(),
			required: $id('nft_f_required').checked,
			list: $id('nft_f_list').checked,
			options: {}
		};
		document.querySelectorAll('#nft_options_body [data-opt]').forEach(function (input) {
			var name = input.getAttribute('data-opt');
			if (input.type === 'checkbox') { if (input.checked) { f.options[name] = true; } }
			else if (input.value !== '') { f.options[name] = input.value; }
		});
		if (def.source === 'items' || def.source === 'items+ajax') {
			if (def.source === 'items+ajax' && sourceMode() === 'ajax') {
				f.ajax = {
					collection: $id('nft_a_collection').value.trim(), label: $id('nft_a_label').value, value: $id('nft_a_value').value.trim(),
					columns: $id('nft_a_columns').value, match: $id('nft_a_match').value.trim(), limit: $id('nft_a_limit').value
				};
			} else {
				f.items = $id('nft_items').value;
			}
		}
		if (def.source === 'suggest' && $id('nft_s_mode').value) {
			f.suggest = { mode: $id('nft_s_mode').value, items: $id('nft_s_items').value, collection: $id('nft_s_collection').value.trim(), column: $id('nft_s_column').value.trim() };
		}
		if (type === 'embededArray') {
			f.subfields = [];
			document.querySelectorAll('#nft_sub_body tr').forEach(function (row) {
				f.subfields.push({
					field: row.querySelector('.nft-s-field').value.trim(),
					type: row.querySelector('.nft-s-type').value,
					short_description: row.querySelector('.nft-s-label').value.trim(),
					required: row.querySelector('.nft-s-required').checked,
					list: row.querySelector('.nft-s-list').checked,
					items: row.querySelector('.nft-s-items').value
				});
			});
		}
		return f;
	}
	function duplicate(name) {
		return fields.some(function (f, i) { return i !== editing && f.field === name; });
	}

	// ---------------------------------------------------------------- eventos
	$id('nft_add').addEventListener('click', function () { open(-1); });
	$id('nft_cancel').addEventListener('click', function () { dialog.close(); });
	$id('nft_f_type').addEventListener('change', function () { renderOptions(this.value, {}); updatePanels(); });
	$id('nft_s_mode').addEventListener('change', updatePanels);
	document.querySelectorAll('input[name="nft_src"]').forEach(function (r) { r.addEventListener('change', updatePanels); });
	$id('nft_a_collection').addEventListener('change', function () { loadPaths(this.value.trim(), 'nft_a_paths'); });
	$id('nft_s_collection').addEventListener('change', function () { loadPaths(this.value.trim(), 'nft_s_paths'); });
	$id('nft_collection').addEventListener('change', function () { loadPaths(collectionName(), 'nft_paths'); });
	$id('nft_f_field').addEventListener('change', function () {
		if (!$id('nft_f_label').value.trim() && this.value.trim()) {
			var t = this.value.trim().replace(/[._]/g, ' ');
			$id('nft_f_label').value = t.charAt(0).toUpperCase() + t.slice(1);
		}
	});
	$id('nft_sub_add').addEventListener('click', function () { $id('nft_sub_body').appendChild(subRow()); });

	$id('nft_ok').addEventListener('click', function () {
		var f = readDialog();
		if (duplicate(f.field)) { errorsBox('nft_dialog_errors', ['Ya hay un campo ' + f.field + '.']); return; }
		// El servidor valida y normaliza con las mismas reglas con que se guardará.
		post({ op: 'validar', field: JSON.stringify(f), collection: collectionName() }).done(function (r) {
			if (r && r.errores && r.errores.length) { errorsBox('nft_dialog_errors', r.errores); return; }
			if (!r || !r.field) { errorsBox('nft_dialog_errors', [(r && r.error) || 'Respuesta inválida.']); return; }
			if (editing >= 0) { fields[editing] = r.field; } else { fields.push(r.field); }
			dialog.close();
			render();
		}).fail(fail);
	});
	$id('nft_try').addEventListener('click', function () {
		post({ op: 'probar', field: JSON.stringify(readDialog()), collection: collectionName() }).done(function (r) {
			var box = $id('nft_test');
			box.textContent = '';
			if (r && r.errores && r.errores.length) { errorsBox('nft_dialog_errors', r.errores); show('nft_test', false); return; }
			if (r && typeof r.error === 'string') { errorsBox('nft_dialog_errors', [r.error]); return; }
			errorsBox('nft_dialog_errors', []);
			var items = (r && r.items) || [];
			box.appendChild(el('strong', { text: items.length ? 'Primeros resultados:' : 'Sin resultados.' }));
			if (items.length) {
				box.appendChild(el('ul', {}, items.map(function (i) { return el('li', { text: i.label + (i.value !== i.label ? '  →  ' + i.value : '') }); })));
			}
			show('nft_test', true);
		}).fail(fail);
	});
	$id('nft_code').addEventListener('click', function () {
		post({ op: 'codigo', field: JSON.stringify(readDialog()), collection: collectionName() }).done(function (r) {
			if (r && r.errores && r.errores.length) { errorsBox('nft_dialog_errors', r.errores); return; }
			errorsBox('nft_dialog_errors', []);
			var box = $id('nft_test');
			box.textContent = '';
			var pre = el('pre', { text: (r && r.code) || '' });
			box.appendChild(el('div', { className: 'd-flex flex-justify-between' }, [
				el('strong', { text: 'Código PHP (página del sitio con $dataset):' }),
				el('button', { type: 'button', className: 'button small', onclick: function () { navigator.clipboard && navigator.clipboard.writeText(pre.textContent); toast('Copiado'); } }, ['Copiar'])
			]));
			box.appendChild(pre);
			show('nft_test', true);
		}).fail(fail);
	});
	$id('nft_detect').addEventListener('click', function () {
		var c = collectionName();
		if (!c) { Swal.fire('Escriba primero la colección'); return; }
		post({ op: 'detectar', collection: c }).done(function (r) {
			if (r && typeof r.error === 'string') { Swal.fire('No se pudo detectar', r.error, 'error'); return; }
			var known = {}, added = 0;
			fields.forEach(function (f) { known[f.field] = true; });
			((r && r.fields) || []).forEach(function (f) { if (!known[f.field]) { fields.push(f); added++; } });
			render();
			toast(added ? added + ' campo(s) agregados: revise tipos y etiquetas.' : 'No se encontraron campos nuevos.');
		});
	});
	$id('nft_save').addEventListener('click', function () {
		sync();
		var data = {};
		$('#nft_form').serializeArray().forEach(function (p) { data[p.name] = p.value; });
		data.op = 'save';
		post(data).done(function (r) {
			if (r && r.errores && r.errores.length) { errorsBox('nft_errors', r.errores); return; }
			if (r && typeof r.error === 'string' && r.error) { errorsBox('nft_errors', [r.error]); return; }
			errorsBox('nft_errors', []);
			if (r && r.fields) { fields = r.fields; render(); }
			toast('Tabla guardada');
		}).fail(function (xhr) { errorsBox('nft_errors', ['Error al guardar (' + xhr.status + ').']); });
	});

	(NFT.collections || []).forEach(function (c) { $id('nft_collections').appendChild(el('option', { value: c })); });
	render();
})();
