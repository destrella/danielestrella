<?php
declare(strict_types=1);

session_start();
require_once __DIR__.'/lib.php';
cms_require_local();

if (empty($_SESSION['cms_token'])) {
	$_SESSION['cms_token'] = bin2hex(random_bytes(16));
}

$token = (string)$_SESSION['cms_token'];
$focusRow = (string)($_SESSION['cms_focus'] ?? ($_GET['focus'] ?? ''));
unset($_SESSION['cms_focus']);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
	try {
		if (!hash_equals($token, (string)($_POST['token'] ?? ''))) {
			throw new RuntimeException('Token inválido, recarga el CMS e intenta de nuevo.');
		}
		$action = (string)($_POST['action'] ?? '');
		if ($action === 'save') {
			$oldFile = trim((string)($_POST['file'] ?? ''));
			$returnUrl = cms_safe_return_url((string)($_POST['return_url'] ?? ''));
			$result = cms_save_entry($_POST, $oldFile !== '' ? $oldFile : null);
			$rowId = cms_row_id($result['file']);
			cms_flash('ok', 'Entrada guardada en '.$result['url'].'. Archivo y sitemap regenerados.', $rowId);
			cms_redirect(cms_return_url_with_focus($returnUrl, $rowId));
		}
		if ($action === 'quick_title') {
			$file = trim((string)($_POST['file'] ?? ''));
			$result = cms_update_entry_title($file, (string)($_POST['title'] ?? ''));
			cms_flash('ok', 'Título actualizado. Nueva ruta: '.$result['url'].'.', cms_row_id($result['file']));
			cms_redirect('index.php');
		}
		if ($action === 'delete') {
			$file = trim((string)($_POST['file'] ?? ''));
			$result = cms_delete_entry($file);
			cms_flash('ok', 'Entrada retirada del archivo publico y movida a '.$result['trash'].'.');
			cms_redirect('index.php');
		}
		if ($action === 'combine') {
			$target = trim((string)($_POST['target'] ?? ''));
			$sources = $_POST['sources'] ?? [];
			if (!is_array($sources)) {
				$sources = [];
			}
			$result = cms_combine_entries($target, $sources);
			$count = count($result['moved']);
			cms_flash('ok', 'Entradas combinadas: '.$count.' origen(es) se movieron a papelera y el destino fue regenerado.', cms_row_id($result['target']['file']));
			cms_redirect('index.php');
		}
		throw new RuntimeException('Acción no reconocida.');
	} catch (Throwable $e) {
		cms_flash('error', $e->getMessage(), isset($_POST['file']) ? cms_row_id((string)$_POST['file']) : '');
		cms_redirect('index.php');
	}
}

$entries = cms_scan_entries();
$flash = cms_take_flash();
$mode = (string)($_GET['mode'] ?? 'list');
$editFile = trim((string)($_GET['edit'] ?? ''));
$formValues = null;
$formTitle = 'Crear entrada';
$formReturnUrl = 'index.php';

if ($editFile !== '') {
	try {
		$abs = cms_resolve_entry_file($editFile);
		$entry = cms_parse_entry_file($abs, $editFile);
		if ($entry === null) {
			throw new RuntimeException('No se pudo leer la entrada.');
		}
		$formValues = cms_entry_form_defaults($entry);
		$formTitle = 'Editar entrada';
		$formReturnUrl = cms_return_url_with_focus(cms_safe_return_url((string)($_GET['return'] ?? '')), cms_row_id($editFile));
		$mode = 'form';
	} catch (Throwable $e) {
		$flash[] = ['type' => 'error', 'text' => $e->getMessage()];
		$mode = 'list';
	}
} elseif ($mode === 'new') {
	$formValues = cms_entry_form_defaults(null);
	$formTitle = 'Crear entrada';
	$formReturnUrl = cms_safe_return_url((string)($_GET['return'] ?? ''));
	$mode = 'form';
}

function cms_render_media_thumb(array $entry): string {
	$media = $entry['media'];
	$src = (string)($media['src'] ?? '');
	if (($media['type'] ?? '') === 'image' && $src !== '') {
		return '<img src="'.cms_h($src).'" alt="" loading="lazy">';
	}
	if (($media['type'] ?? '') === 'video' && $src !== '') {
		$poster = cms_preview_image_src((string)($media['poster'] ?? ''));
		$thumb = $poster !== '' ? $poster : cms_video_frame_thumb($src);
		$thumb = $thumb !== '' ? $thumb : CMS_DEFAULT_PREVIEW_IMAGE;
		return '<img src="'.cms_h($thumb).'" alt="" loading="lazy">';
	}
	return '<span>Sin media</span>';
}

function cms_video_frame_thumb(string $src): string {
	$src = trim($src);
	if ($src === '') {
		return '';
	}
	$cacheDir = __DIR__.'/cache/video-thumbs';
	$cacheRel = 'cache/video-thumbs/'.sha1($src).'.jpg';
	$cacheAbs = __DIR__.'/'.$cacheRel;
	if (is_file($cacheAbs) && filesize($cacheAbs) > 0) {
		return $cacheRel;
	}
	$ffmpeg = cms_ffmpeg_path();
	if ($ffmpeg === '' || !function_exists('exec')) {
		return '';
	}
	if (!is_dir($cacheDir)) {
		mkdir($cacheDir, 0755, true);
	}
	$input = cms_video_frame_input($src);
	$tmp = $cacheAbs.'.tmp.jpg';
	@unlink($tmp);
	foreach (['1', '0.7', '0.2', '0'] as $seek) {
		$seekArgs = $seek !== '0' ? ' -ss '.escapeshellarg($seek) : '';
		$command = escapeshellarg($ffmpeg).
			' -hide_banner -loglevel error -y'.$seekArgs.
			' -i '.escapeshellarg($input).
			' -frames:v 1 -vf '.escapeshellarg('scale=320:-1').
			' '.escapeshellarg($tmp).' 2>&1';
		$output = [];
		$code = 1;
		exec($command, $output, $code);
		if ($code === 0 && is_file($tmp) && filesize($tmp) > 0) {
			rename($tmp, $cacheAbs);
			return $cacheRel;
		}
		@unlink($tmp);
	}
	return '';
}

function cms_video_frame_input(string $src): string {
	if (str_starts_with($src, '//')) {
		return 'https:'.$src;
	}
	if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
		return $src;
	}
	$relative = ltrim($src, '/');
	$local = CMS_ROOT.'/'.$relative;
	return is_file($local) ? $local : $src;
}

function cms_ffmpeg_path(): string {
	static $ffmpeg = null;
	if ($ffmpeg !== null) {
		return $ffmpeg;
	}
	foreach (['/opt/homebrew/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/bin/ffmpeg'] as $candidate) {
		if (is_executable($candidate)) {
			return $ffmpeg = $candidate;
		}
	}
	if (function_exists('exec')) {
		$output = [];
		$code = 1;
		exec('command -v ffmpeg 2>/dev/null', $output, $code);
		$candidate = trim((string)($output[0] ?? ''));
		if ($code === 0 && $candidate !== '') {
			return $ffmpeg = $candidate;
		}
	}
	return $ffmpeg = '';
}

function cms_media_type_key(array $entry): string {
	$type = (string)($entry['media']['type'] ?? 'none');
	return in_array($type, ['image', 'video'], true) ? $type : 'none';
}

function cms_media_type_label(string $type): string {
	return match ($type) {
		'image' => 'Imagen',
		'video' => 'Video',
		default => 'Sin media',
	};
}

$providerOptions = array_values(array_unique(array_map(fn(array $entry): string => (string)$entry['provider'], $entries)));
sort($providerOptions, SORT_NATURAL | SORT_FLAG_CASE);
$mediaTypeOptions = [];
foreach ($entries as $entry) {
	$type = cms_media_type_key($entry);
	$mediaTypeOptions[$type] = cms_media_type_label($type);
}
asort($mediaTypeOptions, SORT_NATURAL | SORT_FLAG_CASE);

function cms_selected(string $actual, string $expected): string {
	return $actual === $expected ? ' selected' : '';
}

function cms_safe_return_url(string $url): string {
	$url = trim(html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	if ($url === '') {
		return 'index.php';
	}
	if (!preg_match('/^index\.php(?:\?[^#]*)?(?:#[A-Za-z0-9_.:-]+)?$/', $url)) {
		return 'index.php';
	}
	parse_str((string)(parse_url($url, PHP_URL_QUERY) ?? ''), $params);
	if (isset($params['edit'])) {
		return 'index.php';
	}
	return $url;
}

function cms_return_url_with_focus(string $url, string $rowId): string {
	$url = cms_safe_return_url($url);
	parse_str((string)(parse_url($url, PHP_URL_QUERY) ?? ''), $params);
	unset($params['edit'], $params['mode']);
	if ($rowId !== '') {
		$params['focus'] = $rowId;
	}
	$query = http_build_query($params);
	return 'index.php'.($query !== '' ? '?'.$query : '');
}

function cms_render_media_style_select(string $name, string $value): void {
	?>
	<select name="<?= cms_h($name) ?>" data-media-style-select>
		<?php foreach (cms_media_style_options() as $class => $label): ?>
			<option value="<?= cms_h($class) ?>"<?= cms_selected($value, (string)$class) ?>><?= cms_h($label) ?></option>
		<?php endforeach; ?>
	</select>
	<?php
}

function cms_render_media_item_fields(array $item, string $prefix, int|string $index): void {
	$type = cms_normalize_media_form_type((string)($item['type'] ?? 'image'));
	$posterHidden = $type !== 'video' ? ' is-hidden' : '';
	$liveHidden = $type !== 'live-photo' ? ' is-hidden' : '';
	?>
	<details class="media-item" data-media-item open>
		<summary class="media-item__summary">
			<span>Media <span data-media-number><?= is_int($index) ? (string)($index + 1) : '' ?></span></span>
			<span class="media-item__meta" data-media-summary></span>
		</summary>
		<div class="media-item__body">
			<input type="hidden" name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][alt]" value="<?= cms_h((string)($item['alt'] ?? '')) ?>">
			<div class="media-item__grid">
				<label class="field media-item__route">
					<span data-media-primary-label>Ruta del archivo</span>
					<input name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][route]" list="entry-image-options" type="text" value="<?= cms_h((string)($item['route'] ?? '')) ?>" data-media-route>
				</label>
				<label class="field">
					<span>Tipo de media</span>
					<select name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][type]" data-media-type-select>
						<option value="image"<?= cms_selected($type, 'image') ?>>Imagen</option>
						<option value="video"<?= cms_selected($type, 'video') ?>>Video</option>
						<option value="live-photo"<?= cms_selected($type, 'live-photo') ?>>Live-photo</option>
					</select>
				</label>
				<label class="field">
					<span>Clase CSS</span>
					<?php cms_render_media_style_select($prefix.'['.(string)$index.'][style]', (string)($item['style'] ?? '')); ?>
				</label>
				<label class="field<?= $liveHidden ?>" data-live-video-field>
					<span>Ruta del video live-photo</span>
					<input name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][video]" type="text" value="<?= cms_h((string)($item['video'] ?? '')) ?>" data-media-live-video>
				</label>
				<label class="field<?= $posterHidden ?>" data-poster-field>
					<span>Poster del video</span>
					<input name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][poster]" list="entry-image-options" type="text" value="<?= cms_h((string)($item['poster'] ?? '')) ?>" data-media-poster>
				</label>
				<label class="field">
					<span>Ancho</span>
					<input name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][width]" type="number" min="0" step="1" value="<?= cms_h((string)($item['width'] ?? '')) ?>" data-media-width>
				</label>
				<label class="field">
					<span>Alto</span>
					<input name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][height]" type="number" min="0" step="1" value="<?= cms_h((string)($item['height'] ?? '')) ?>" data-media-height>
				</label>
			</div>
			<label class="field">
				<span>Figcaption HTML</span>
				<textarea name="<?= cms_h($prefix) ?>[<?= cms_h((string)$index) ?>][caption]" rows="4" spellcheck="false" placeholder="<details><summary>Prompt</summary><p>Texto...</p></details>" data-media-caption><?= cms_h((string)($item['caption'] ?? '')) ?></textarea>
			</label>
			<div class="inline-actions">
				<button class="button danger small" type="button" data-remove-media-item>Eliminar media</button>
			</div>
		</div>
	</details>
	<?php
}

function cms_render_block_fields(array $block, int|string $index): void {
	$prefix = 'blocks['.$index.']';
	$layout = (string)$block['layout'];
	$mediaItems = cms_media_form_items_from_block($block);
	?>
	<details class="section-block" data-section-block open>
		<summary class="section-block__summary">
			<span class="section-block__title">Bloque <span data-block-number><?= is_int($index) ? (string)($index + 1) : '' ?></span></span>
			<span class="section-block__meta" data-block-summary></span>
		</summary>
		<div class="section-block__body">
			<div class="section-block__actions">
				<button class="button danger small" type="button" data-remove-block>Eliminar bloque</button>
			</div>
		<div class="block-grid">
			<label class="field">
				<span>Formato de sección</span>
				<select name="<?= cms_h($prefix) ?>[layout]" data-layout-select>
					<option value="texto"<?= cms_selected($layout, 'texto') ?>>.texto - una columna</option>
					<option value="imagen-y-texto"<?= cms_selected($layout, 'imagen-y-texto') ?>>.imagen-y-texto - texto + media</option>
					<option value="columnas-texto"<?= cms_selected($layout, 'columnas-texto') ?>>.columnas-texto - dos textos</option>
				</select>
			</label>
			<label class="field<?= $layout !== 'imagen-y-texto' ? ' is-hidden' : '' ?>" data-side-field>
				<span>Posición de media</span>
				<select name="<?= cms_h($prefix) ?>[side]">
					<option value="imagen-izquierda"<?= cms_selected((string)$block['side'], 'imagen-izquierda') ?>>.imagen-izquierda</option>
					<option value="imagen-derecha"<?= cms_selected((string)$block['side'], 'imagen-derecha') ?>>.imagen-derecha</option>
				</select>
			</label>
			<label class="field">
				<span>Alineación de texto</span>
				<select name="<?= cms_h($prefix) ?>[align]">
					<option value="left"<?= cms_selected((string)$block['align'], 'left') ?>>Izquierda</option>
					<option value="center"<?= cms_selected((string)$block['align'], 'center') ?>>Centrado</option>
					<option value="right"<?= cms_selected((string)$block['align'], 'right') ?>>Derecha</option>
				</select>
			</label>
			<label class="field">
				<span>Estilo multimedia</span>
				<select name="<?= cms_h($prefix) ?>[mediaStyle]">
					<option value=""<?= cms_selected((string)$block['mediaStyle'], '') ?>>Sin media</option>
					<option value="simple"<?= cms_selected((string)$block['mediaStyle'], 'simple') ?>>Media simple</option>
					<option value="live-photo"<?= cms_selected((string)$block['mediaStyle'], 'live-photo') ?>>.live-photo</option>
					<option value="galeria-imagenes"<?= cms_selected((string)$block['mediaStyle'], 'galeria-imagenes') ?>>.galeria-imagenes</option>
				</select>
			</label>
		</div>
		<details class="help-box block-help">
			<summary>Guía de este bloque</summary>
			<p data-guide-for="texto" class="<?= $layout !== 'texto' ? 'is-hidden' : '' ?>"><code>.texto</code> genera una sola columna. Puede contener texto, multimedia o ambos; usa <code>{{media:1}}</code>, <code>{{media:2}}</code> para colocar medios individuales entre párrafos, o <code>{{galería}}</code> para insertar todos los medios del bloque en ese punto.</p>
			<p data-guide-for="imagen-y-texto" class="<?= $layout !== 'imagen-y-texto' ? 'is-hidden' : '' ?>"><code>.imagen-y-texto</code> genera una columna <code>.texto-columna</code> y una columna multimedia. El selector de posición agrega <code>.imagen-izquierda</code> o <code>.imagen-derecha</code>.</p>
			<p data-guide-for="columnas-texto" class="<?= $layout !== 'columnas-texto' ? 'is-hidden' : '' ?>"><code>.columnas-texto</code> usa los campos <strong>Texto con HTML</strong> y <strong>Texto con HTML - segunda columna</strong> como dos columnas de texto.</p>
		</details>
		<label class="field">
			<span>Subtítulo h2</span>
			<input name="<?= cms_h($prefix) ?>[subtitle]" type="text" value="<?= cms_h((string)$block['subtitle']) ?>">
		</label>
		<label class="field">
			<span>Texto con HTML</span>
			<textarea name="<?= cms_h($prefix) ?>[html]" rows="9" spellcheck="false"><?= cms_h((string)$block['html']) ?></textarea>
		</label>
		<label class="field<?= $layout !== 'columnas-texto' ? ' is-hidden' : '' ?>" data-second-column>
			<span>Texto con HTML - segunda columna</span>
			<textarea name="<?= cms_h($prefix) ?>[html2]" rows="7" spellcheck="false"><?= cms_h((string)$block['html2']) ?></textarea>
		</label>
		<div class="media-editor" data-media-editor>
			<div class="media-editor__heading">
				<div>
					<span class="field-title">Multimedia</span>
					<p class="subtle">Cada grupo genera una imagen, video o live-photo. Puedes colapsarlos para mantener limpio el bloque.</p>
				</div>
				<button class="button ghost small" type="button" data-add-media-item>Agregar multimedia</button>
			</div>
			<div class="media-items" data-media-items>
				<?php foreach ($mediaItems as $mediaIndex => $mediaItem): ?>
					<?php cms_render_media_item_fields($mediaItem, $prefix.'[mediaItems]', (int)$mediaIndex); ?>
				<?php endforeach; ?>
			</div>
			<template data-media-item-template>
				<?php cms_render_media_item_fields(cms_media_form_default_item(), $prefix.'[mediaItems]', '__MEDIA_INDEX__'); ?>
			</template>
		</div>
		</div>
	</details>
	<?php
}

function cms_form_page(array $values, string $title, string $token, string $returnUrl): void {
	$isEdit = $values['file'] !== '';
	$imageOptions = array_values(array_unique(array_filter($values['imageOptions'] ?? [])));
	?>
	<section class="panel form-panel">
		<div class="panel-heading">
			<div>
				<p class="eyebrow">Entrada estatica</p>
				<h2><?= cms_h($title) ?></h2>
			</div>
			<a class="button ghost" href="<?= cms_h($returnUrl) ?>">Volver al listado</a>
		</div>
		<form class="entry-form" method="post">
			<input type="hidden" name="token" value="<?= cms_h($token) ?>">
			<input type="hidden" name="action" value="save">
			<input type="hidden" name="file" value="<?= cms_h($values['file']) ?>">
			<input type="hidden" name="content_mode" value="blocks">
			<input type="hidden" name="return_url" value="<?= cms_h($returnUrl) ?>">

			<div class="grid-2">
				<label class="field">
					<span>Titulo</span>
					<input name="title" type="text" value="<?= cms_h($values['title']) ?>" required>
				</label>
				<label class="field">
					<span>Fecha y hora</span>
					<input name="date" type="datetime-local" step="1" value="<?= cms_h($values['date']) ?>" required>
				</label>
			</div>

			<label class="field">
				<span>Descripcion SEO</span>
				<input name="summary" type="text" value="<?= cms_h($values['summary']) ?>" maxlength="180">
			</label>

			<div class="grid-2">
				<label class="field">
					<span>Miniatura de preview</span>
					<input name="preview_image" list="entry-image-options" type="text" value="<?= cms_h($values['previewImage']) ?>" placeholder="Vacío: primera imagen de los bloques">
				</label>
				<label class="field">
					<span>Imagen de metadatos</span>
					<input name="meta_image" list="entry-image-options" type="text" value="<?= cms_h($values['metaImage']) ?>" placeholder="Vacío: imagen genérica del sitio">
				</label>
			</div>
			<datalist id="entry-image-options">
				<?php foreach ($imageOptions as $image): ?>
					<option value="<?= cms_h($image) ?>"></option>
				<?php endforeach; ?>
			</datalist>

			<details class="help-box">
				<summary>Instrucciones para bloques, HTML y multimedia</summary>
				<p>Cada bloque genera una etiqueta <code>&lt;section&gt;</code>. Usa <code>.imagen-y-texto</code> con <code>.imagen-izquierda</code> o <code>.imagen-derecha</code>, <code>.columnas-texto</code> para dos columnas de texto, o <code>.texto</code> para una sola columna con texto y/o multimedia.</p>
				<p>HTML permitido en textos y pies: <code>h3</code>, <code>h4</code>, <code>h5</code>, <code>h6</code>, <code>a</code>, <code>strong</code>, <code>em</code>, <code>i</code>, <code>ol</code>, <code>ul</code>, <code>li</code>, <code>code</code>, <code>quote</code>, <code>blockquote</code>, <code>small</code>, <code>iframe</code>, <code>details</code> y <code>summary</code>.</p>
				<p>Multimedia: agrega un grupo por cada imagen, video o live-photo. Para conservar medios intercalados en el texto usa <code>{{media:1}}</code>, <code>{{media:2}}</code> o <code>{{galería}}</code>; el marcador de galería inserta todos los multimedia del bloque.</p>
			</details>

			<div class="section-blocks" data-section-blocks>
				<?php foreach (($values['blocks'] ?? [cms_default_block()]) as $index => $block): ?>
					<?php cms_render_block_fields($block, (int)$index); ?>
				<?php endforeach; ?>
			</div>
			<template id="section-block-template">
				<?php cms_render_block_fields(cms_default_block(), '__INDEX__'); ?>
			</template>
			<button class="button ghost" type="button" data-add-block>Agregar bloque</button>

			<div class="actions">
				<button class="button primary" type="submit"><?= $isEdit ? 'Guardar cambios' : 'Crear entrada' ?></button>
				<a class="button ghost" href="<?= cms_h($returnUrl) ?>">Cancelar</a>
				<?php if ($isEdit): ?>
					<a class="button ghost" href="<?= cms_h('/'.dirname($values['file']).'/') ?>" target="_blank" rel="noopener">Ver publicada</a>
				<?php endif; ?>
			</div>
		</form>
	</section>
	<?php
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>CMS de entradas estáticas</title>
	<link rel="stylesheet" href="assets/cms.css?v=<?= (int)filemtime(__DIR__.'/assets/cms.css') ?>">
</head>
<body data-focus-row="<?= cms_h($focusRow) ?>">
	<header class="app-header">
		<div>
			<p class="eyebrow">Daniel Estrella</p>
			<h1>CMS de entradas estáticas</h1>
			<p class="subtle"><span id="visible-count"><?= cms_h((string)count($entries)) ?></span> de <?= cms_h((string)count($entries)) ?> entradas visibles</p>
		</div>
		<nav class="header-actions" aria-label="Acciones principales">
			<a class="button primary" href="index.php?mode=new">Nueva entrada</a>
			<a class="button ghost" href="/archivo/" target="_blank" rel="noopener">Archivo público</a>
		</nav>
	</header>

	<main class="layout">
		<?php foreach ($flash as $message): ?>
			<p class="notice <?= ($message['type'] ?? '') === 'error' ? 'error' : 'ok' ?>"><?= cms_h((string)($message['text'] ?? '')) ?></p>
		<?php endforeach; ?>

		<?php if ($mode === 'form' && $formValues !== null): ?>
			<?php cms_form_page($formValues, $formTitle, $token, $formReturnUrl); ?>
		<?php else: ?>
			<section class="panel">
				<div class="panel-heading">
					<div>
						<p class="eyebrow">Vista temporal integrada</p>
						<h2>Entradas existentes</h2>
					</div>
					<div class="tools filters" aria-label="Filtros de entradas">
						<label class="filter-field filter-title">
							<span>Buscar título</span>
							<input id="filter-title" class="search" type="search" placeholder="Título de la entrada" autocomplete="off">
						</label>
						<label class="filter-field">
							<span>Desde</span>
							<input id="filter-date-from" type="date">
						</label>
						<label class="filter-field">
							<span>Hasta</span>
							<input id="filter-date-to" type="date">
						</label>
						<label class="filter-field">
							<span>Proveedor</span>
							<select id="filter-provider">
								<option value="">Todos</option>
								<?php foreach ($providerOptions as $provider): ?>
									<option value="<?= cms_h($provider) ?>"><?= cms_h($provider) ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<label class="filter-field">
							<span>Tipo media</span>
							<select id="filter-media-type">
								<option value="">Todos</option>
								<?php foreach ($mediaTypeOptions as $type => $label): ?>
									<option value="<?= cms_h($type) ?>"><?= cms_h($label) ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						<button class="button ghost small" type="button" data-clear-filters>Limpiar</button>
					</div>
				</div>

				<form id="combine-form" class="combine-bar" method="post">
					<input type="hidden" name="token" value="<?= cms_h($token) ?>">
					<input type="hidden" name="action" value="combine">
					<label>
						<span>Destino para combinar</span>
						<select name="target" required>
							<?php foreach ($entries as $entry): ?>
								<option value="<?= cms_h($entry['file']) ?>"><?= cms_h($entry['dateText'].' - '.$entry['title']) ?></option>
							<?php endforeach; ?>
						</select>
					</label>
					<button class="button" type="submit" data-combine disabled>Combinar seleccionadas</button>
				</form>

				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th class="col-select">Sel.</th>
								<th class="col-media">Media</th>
								<th class="col-title">Título</th>
								<th class="col-date">Fecha y hora</th>
								<th>Texto</th>
								<th class="col-provider">Proveedor media</th>
								<th class="col-actions">Acciones</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($entries as $entry): ?>
								<?php $rowId = cms_row_id($entry['file']); ?>
								<?php $mediaType = cms_media_type_key($entry); ?>
								<?php $dateValue = $entry['dateIso'] !== '' ? substr((string)$entry['dateIso'], 0, 10) : ''; ?>
								<tr id="<?= cms_h($rowId) ?>" data-row data-row-id="<?= cms_h($rowId) ?>" data-title="<?= cms_h(mb_strtolower($entry['title'], 'UTF-8')) ?>" data-date="<?= cms_h($dateValue) ?>" data-provider="<?= cms_h($entry['provider']) ?>" data-media-type="<?= cms_h($mediaType) ?>" tabindex="-1">
									<td>
										<input class="select-entry" type="checkbox" name="sources[]" value="<?= cms_h($entry['file']) ?>" form="combine-form" aria-label="Seleccionar <?= cms_h($entry['title']) ?>">
									</td>
									<td>
										<div class="media-thumb"><?= cms_render_media_thumb($entry) ?></div>
									</td>
									<td>
										<div class="title-view" data-title-view>
											<a href="<?= cms_h($entry['url']) ?>" target="_blank" rel="noopener"><?= cms_h($entry['title']) ?></a>
											<span class="path"><?= cms_h($entry['url']) ?></span>
										</div>
										<form class="inline-edit" method="post" data-edit-form>
											<input type="hidden" name="token" value="<?= cms_h($token) ?>">
											<input type="hidden" name="action" value="quick_title">
											<input type="hidden" name="file" value="<?= cms_h($entry['file']) ?>">
											<input type="text" name="title" value="<?= cms_h($entry['title']) ?>" required>
											<div class="inline-actions">
												<button class="button primary small" type="submit">Guardar</button>
												<button class="button ghost small" type="button" data-cancel>Cancelar</button>
											</div>
										</form>
									</td>
									<td>
										<?php if ($entry['dateIso'] !== ''): ?>
											<time datetime="<?= cms_h($entry['dateIso']) ?>"><?= cms_h($entry['dateText']) ?></time>
										<?php else: ?>
											<span class="path">Sin fecha</span>
										<?php endif; ?>
									</td>
									<td><div class="text-scroll"><?= cms_h($entry['text']) ?></div></td>
									<td><span class="provider"><?= cms_h($entry['provider']) ?></span></td>
									<td>
										<div class="row-actions">
											<button class="button small" type="button" data-edit>Editar título</button>
											<a class="button ghost small" href="index.php?edit=<?= rawurlencode($entry['file']) ?>&amp;return=<?= rawurlencode('index.php?focus='.$rowId) ?>" data-entry-edit-link>Editar</a>
											<form method="post" data-delete-form>
												<input type="hidden" name="token" value="<?= cms_h($token) ?>">
												<input type="hidden" name="action" value="delete">
												<input type="hidden" name="file" value="<?= cms_h($entry['file']) ?>">
												<button class="button danger small" type="submit">Eliminar</button>
											</form>
										</div>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
			</section>
		<?php endif; ?>
	</main>
	<script src="assets/cms.js?v=<?= (int)filemtime(__DIR__.'/assets/cms.js') ?>"></script>
</body>
</html>
