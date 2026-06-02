<?php
declare(strict_types=1);

ini_set('pcre.jit', '0');
date_default_timezone_set('America/Merida');

define('CMS_ROOT', dirname(__DIR__));
define('CMS_DOMAIN', 'https://danielestrella.com');
define('CMS_TIMEZONE', 'America/Merida');
define('CMS_PER_PAGE', 18);
define('CMS_TRASH_DIR', __DIR__.'/trash');
define('CMS_DEFAULT_PREVIEW_IMAGE', '/portada.webp');

function cms_is_local_request(): bool {
	if (PHP_SAPI === 'cli') {
		return true;
	}
	$host = strtolower((string)($_SERVER['HTTP_HOST'] ?? ''));
	$host = preg_replace('/:\d+$/', '', $host) ?: $host;
	return in_array($host, ['blog.local', 'localhost', '127.0.0.1', '::1'], true);
}

function cms_require_local(): void {
	if (cms_is_local_request()) {
		return;
	}
	http_response_code(403);
	echo 'CMS disponible solo en entorno local.';
	exit;
}

function cms_h(string|int|float|null $value): string {
	return htmlspecialchars((string)$value, ENT_COMPAT | ENT_SUBSTITUTE, 'UTF-8');
}

function cms_normalize_space(string $text): string {
	$text = str_replace(["\r\n", "\r"], "\n", $text);
	$text = preg_replace('/[ \t]+/u', ' ', $text) ?? $text;
	$text = preg_replace('/ *\n+ */u', "\n", $text) ?? $text;
	$text = preg_replace('/\n{3,}/u', "\n\n", $text) ?? $text;
	return trim($text);
}

function cms_html_text(string $html): string {
	$html = preg_replace('/<br\s*\/?>/i', "\n", $html) ?? $html;
	return cms_normalize_space(html_entity_decode(strip_tags($html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
}

function cms_attr(string $tag, string $name): string {
	$quoted = '/\b'.preg_quote($name, '/').'\s*=\s*(["\'])(.*?)\1/is';
	if (preg_match($quoted, $tag, $match)) {
		return html_entity_decode($match[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	$bare = '/\b'.preg_quote($name, '/').'\s*=\s*([^\s>]+)/is';
	if (preg_match($bare, $tag, $match)) {
		return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	return '';
}

function cms_relative_path(string $path): string {
	$path = str_replace('\\', '/', $path);
	$root = str_replace('\\', '/', CMS_ROOT).'/';
	return ltrim(str_replace($root, '', $path), '/');
}

function cms_row_id(string $relativeFile): string {
	$relativeFile = trim(str_replace('\\', '/', $relativeFile), '/');
	if (!preg_match('#^archivo/\d{4}/\d{2}/[^/]+/index\.html$#', $relativeFile)) {
		return '';
	}
	return 'entrada-'.substr(sha1($relativeFile), 0, 12);
}

function cms_resolve_entry_file(string $relativeFile): string {
	$relativeFile = trim(str_replace('\\', '/', $relativeFile), '/');
	if (!preg_match('#^archivo/\d{4}/\d{2}/[^/]+/index\.html$#', $relativeFile)) {
		throw new RuntimeException('Archivo de entrada inválido.');
	}
	$path = realpath(CMS_ROOT.'/'.$relativeFile);
	$archive = realpath(CMS_ROOT.'/archivo');
	if ($path === false || !is_file($path) || $archive === false || strpos($path, $archive.DIRECTORY_SEPARATOR) !== 0) {
		throw new RuntimeException('La entrada no existe o esta fuera del archivo.');
	}
	return $path;
}

function cms_current_css_href(): string {
	$file = CMS_ROOT.'/archivo/index.html';
	$html = is_file($file) ? (string)file_get_contents($file) : '';
	if (preg_match('/<link rel="stylesheet" href="([^"]*\/css\/estilos\.css\?v=[^"]+)"/', $html, $match)) {
		return str_replace(CMS_DOMAIN, '', $match[1]);
	}
	return '/css/estilos.css?v=202605311453';
}

function cms_current_js_src(): string {
	$file = CMS_ROOT.'/archivo/index.html';
	$html = is_file($file) ? (string)file_get_contents($file) : '';
	if (preg_match('/<script src="([^"]*\/js\/scripts\.js\?v=[^"]+)"/', $html, $match)) {
		return str_replace(CMS_DOMAIN, '', $match[1]);
	}
	return '/js/scripts.js?v=202605312155';
}

function cms_logo_svg(): string {
	$file = CMS_ROOT.'/index.html';
	$html = is_file($file) ? (string)file_get_contents($file) : '';
	return (preg_match('/<svg\b[\s\S]*?<\/svg>/', $html, $match)) ? $match[0] : '';
}

function cms_slugify(string $title): string {
	$value = html_entity_decode(strip_tags($title), ENT_QUOTES | ENT_HTML5, 'UTF-8');
	$value = str_replace('&', ' y ', $value);
	if (class_exists('Transliterator')) {
		$trans = Transliterator::create('Any-Latin; Latin-ASCII; Lower()');
		if ($trans) {
			$value = $trans->transliterate($value);
		}
	} else {
		$converted = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
		if ($converted !== false) {
			$value = $converted;
		}
		$value = strtolower($value);
	}
	$value = preg_replace('/[^a-z0-9]+/', '-', strtolower($value)) ?? '';
	$value = trim($value, '-');
	$value = substr($value, 0, 90);
	return rtrim($value, '-') ?: 'entrada';
}

function cms_unique_slug(string $parentDir, string $baseSlug, ?string $currentSlug = null): string {
	$slug = $baseSlug !== '' ? $baseSlug : 'entrada';
	if ($currentSlug !== null && $slug === $currentSlug) {
		return $slug;
	}
	$candidate = $slug;
	$i = 2;
	while (is_dir($parentDir.'/'.$candidate) && $candidate !== $currentSlug) {
		$candidate = $slug.'-'.$i;
		$i++;
	}
	return $candidate;
}

function cms_datetime_from_input(string $input): DateTimeImmutable {
	$input = trim($input);
	if ($input === '') {
		throw new RuntimeException('La fecha no puede quedar vacia.');
	}
	if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/', $input)) {
		$input .= ':00';
	}
	return new DateTimeImmutable($input, new DateTimeZone(CMS_TIMEZONE));
}

function cms_date_input_value(string $iso): string {
	try {
		$date = (new DateTimeImmutable($iso))->setTimezone(new DateTimeZone(CMS_TIMEZONE));
		return $date->format('Y-m-d\TH:i:s');
	} catch (Throwable) {
		return (new DateTimeImmutable('now', new DateTimeZone(CMS_TIMEZONE)))->format('Y-m-d\TH:i:s');
	}
}

function cms_spanish_date(DateTimeImmutable $date, bool $long = true): string {
	static $days = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
	static $months = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
	$local = $date->setTimezone(new DateTimeZone(CMS_TIMEZONE));
	if ($long) {
		return $days[(int)$local->format('N')].', '.(int)$local->format('j').' de '.$months[(int)$local->format('n')].' '.$local->format('Y').' a las '.$local->format('H:i');
	}
	$month = mb_strtolower($months[(int)$local->format('n')], 'UTF-8');
	return (int)$local->format('j').' de '.$month.' de '.$local->format('Y').', '.$local->format('H:i');
}

function cms_local_info(string $iso): array {
	$date = new DateTimeImmutable($iso);
	$local = $date->setTimezone(new DateTimeZone(CMS_TIMEZONE));
	return [
		'date' => $date,
		'local' => $local,
		'year' => $local->format('Y'),
		'month' => $local->format('m'),
		'iso' => $date->format('c'),
		'dateText' => cms_spanish_date($date, true),
		'archiveDate' => cms_spanish_date($date, false),
	];
}

function cms_linkify_text(string $text): string {
	$html = cms_h($text);
	$html = preg_replace_callback('/(https?:\/\/[^\s<]+)/', function (array $match): string {
		$url = preg_replace('/[),.;!?]+$/', '', $match[1]) ?? $match[1];
		$tail = substr($match[1], strlen($url));
		return '<a href="'.cms_h($url).'" target="_blank" rel="noopener nofollow">'.cms_h($url).'</a>'.cms_h($tail);
	}, $html) ?? $html;
	$chunks = preg_split('/(<a\b[^>]*>[\s\S]*?<\/a>)/i', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
	foreach ($chunks as &$chunk) {
		if (preg_match('/^<a\b/i', $chunk)) {
			continue;
		}
		$chunk = preg_replace_callback('/(^|[^A-Za-z0-9_.\/])@([A-Za-z0-9_](?:[A-Za-z0-9._]{0,28}[A-Za-z0-9_])?)/', function (array $match): string {
			$user = $match[2];
			return $match[1].'<a href="https://www.instagram.com/'.cms_h($user).'" target="_blank" rel="noopener nofollow">@'.cms_h($user).'</a>';
		}, $chunk) ?? $chunk;
	}
	unset($chunk);
	return implode('', $chunks);
}

function cms_text_to_html(string $text): string {
	$text = cms_normalize_space($text);
	if ($text === '') {
		return '';
	}
	$blocks = preg_split('/\n{2,}/', $text) ?: [];
	$html = [];
	foreach ($blocks as $block) {
		$lines = array_map('trim', explode("\n", trim($block)));
		$lines = array_values(array_filter($lines, fn(string $line): bool => $line !== ''));
		if (!$lines) {
			continue;
		}
		$html[] = "\t\t\t\t<p>".implode("<br>\n", array_map('cms_linkify_text', $lines)).'</p>';
	}
	return implode("\n", $html);
}

function cms_summary(string $summary, string $text): string {
	$summary = cms_normalize_space($summary);
	if ($summary !== '') {
		return mb_substr($summary, 0, 180, 'UTF-8');
	}
	$plain = cms_normalize_space($text);
	if (mb_strlen($plain, 'UTF-8') <= 160) {
		return $plain;
	}
	$cut = mb_substr($plain, 0, 159, 'UTF-8');
	$space = mb_strrpos($cut, ' ', 0, 'UTF-8');
	return trim($space !== false && $space > 80 ? mb_substr($cut, 0, $space, 'UTF-8') : $cut).'...';
}

function cms_article_html(string $html): string {
	return (preg_match('/<article\b[\s\S]*?<\/article>/i', $html, $match)) ? $match[0] : $html;
}

function cms_extract_title(string $html): string {
	if (preg_match('/<h1\b[^>]*>([\s\S]*?)<\/h1>/i', $html, $match)) {
		return cms_html_text($match[1]);
	}
	if (preg_match('/<title>([\s\S]*?)<\/title>/i', $html, $match)) {
		return cms_normalize_space(str_replace('— Daniel Estrella', '', cms_html_text($match[1])));
	}
	return 'Entrada sin titulo';
}

function cms_extract_date(string $html): array {
	$metaIso = '';
	if (preg_match('/article:published_time" content="([^"]+)"/i', $html, $metaMatch)) {
		$metaIso = html_entity_decode($metaMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	if (preg_match('/<time\b[^>]*datetime="([^"]+)"[^>]*>([\s\S]*?)<\/time>/i', $html, $match)) {
		$timeIso = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return [$metaIso !== '' ? $metaIso : $timeIso, cms_html_text($match[2])];
	}
	if ($metaIso !== '') {
		return [$metaIso, cms_local_info($metaIso)['dateText']];
	}
	return ['', ''];
}

function cms_extract_summary(string $html): string {
	if (preg_match('/<meta\b(?=[^>]*\bname="description")[^>]*\bcontent="([^"]*)"/i', $html, $match)) {
		return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	if (preg_match('/<meta\b(?=[^>]*\bproperty="og:description")[^>]*\bcontent="([^"]*)"/i', $html, $match)) {
		return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
	}
	return '';
}

function cms_extract_meta_image(string $html): string {
	if (preg_match('/<meta\b(?=[^>]*\bproperty="og:image")[^>]*\bcontent="([^"]*)"/i', $html, $match)) {
		$value = html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
		return $value === CMS_DOMAIN.'/portada.webp' ? '' : $value;
	}
	return '';
}

function cms_extract_preview_image(string $html): string {
	if (preg_match('/<meta\b(?=[^>]*\bname="cms:preview_image")[^>]*\bcontent="([^"]*)"/i', $html, $match)) {
		return cms_preview_image_src(html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
	}
	return '';
}

function cms_extract_text(string $html): string {
	$scope = $html;
	if (preg_match('/<section\b[^>]*class="[^"]*texto-columna[^"]*"[^>]*>([\s\S]*?)<\/section>/i', $html, $match)) {
		$scope = $match[1];
	} elseif (preg_match('/<article\b[^>]*>([\s\S]*?)<\/article>/i', $html, $match)) {
		$scope = $match[1];
	}
	$scope = preg_replace('/<figure\b[\s\S]*?<\/figure>/i', "\n", $scope) ?? $scope;
	$scope = preg_replace('/<div\b[^>]*class="[^"]*(?:galeria|gslider)[^"]*"[\s\S]*?<\/div>/i', "\n", $scope) ?? $scope;
	$parts = [];
	if (preg_match_all('/<(p|blockquote|figcaption)\b[^>]*>([\s\S]*?)<\/\1>/i', $scope, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$text = cms_html_text($match[2]);
			if ($text !== '') {
				$parts[] = $text;
			}
		}
	}
	return implode("\n\n", $parts);
}

function cms_extract_content_html(string $html): string {
	$article = cms_article_html($html);
	if (preg_match('/^<article\b[^>]*>([\s\S]*?)<\/article>$/i', trim($article), $match)) {
		$content = $match[1];
	} else {
		$content = $article;
	}
	$content = preg_replace('/^\s*<h1\b[^>]*>[\s\S]*?<\/h1>\s*/i', '', $content, 1) ?? $content;
	$content = preg_replace('/^\s*<p\b[^>]*class="[^"]*fecha-entrada[^"]*"[^>]*>[\s\S]*?<\/p>\s*/i', '', $content, 1) ?? $content;
	return trim($content);
}

function cms_is_complex_entry_html(string $html): bool {
	$content = cms_extract_content_html($html);
	$sectionCount = preg_match_all('/<section\b/i', $content);
	if ($sectionCount > 1) {
		return true;
	}
	return preg_match('/\b(imagen-y-texto|imagen-izquierda|imagen-derecha|espacio-retratos|live-photo|gslider|galeria-imagenes|texto-dos-columnas)\b/i', $content) === 1;
}

function cms_allowed_html(string $html): string {
	$allowed = '<h3><h4><h5><h6><a><strong><em><i><ol><ul><li><code><quote><blockquote><small><iframe><p><br><details><summary>';
	$clean = strip_tags($html, $allowed);
	$clean = preg_replace_callback('/<a\b([^>]*)>/i', function (array $match): string {
		$href = cms_attr($match[0], 'href');
		if ($href === '') {
			return '<a>';
		}
		return '<a href="'.cms_h($href).'" target="_blank" rel="noopener nofollow">';
	}, $clean) ?? $clean;
	$clean = preg_replace_callback('/<iframe\b([^>]*)>/i', function (array $match): string {
		$src = cms_attr($match[0], 'src');
		if ($src === '') {
			return '';
		}
		return '<iframe src="'.cms_h($src).'" loading="lazy" allowfullscreen></iframe>';
	}, $clean) ?? $clean;
	return trim($clean);
}

function cms_allowed_caption_html(string $html): string {
	return cms_allowed_html($html);
}

function cms_split_captions(string $text): array {
	if (trim($text) === '') {
		return [];
	}
	return array_map('trim', preg_split('/^\s*---\s*$/m', $text) ?: []);
}

function cms_first_h2_from_html(string &$html): string {
	if (preg_match('/<h2\b[^>]*>([\s\S]*?)<\/h2>/i', $html, $match)) {
		$html = preg_replace('/<h2\b[^>]*>[\s\S]*?<\/h2>\s*/i', '', $html, 1) ?? $html;
		return cms_html_text($match[1]);
	}
	return '';
}

function cms_inner_html_from_tag(string $html, string $tag, string $classPattern = ''): array {
	$pattern = '/<'.$tag.'\b([^>]*)>([\s\S]*?)<\/'.$tag.'>/i';
	$items = [];
	if (preg_match_all($pattern, $html, $matches, PREG_SET_ORDER)) {
		foreach ($matches as $match) {
			$class = cms_attr($match[0], 'class');
			if ($classPattern !== '' && !preg_match($classPattern, $class)) {
				continue;
			}
			$items[] = ['tag' => $match[0], 'attrs' => $match[1], 'html' => trim($match[2]), 'class' => $class];
		}
	}
	return $items;
}

function cms_extract_figcaption_html(string $figureHtml): string {
	if (preg_match('/<figcaption\b[^>]*>([\s\S]*?)<\/figcaption>/i', $figureHtml, $match)) {
		return trim($match[1]);
	}
	return '';
}

function cms_div_blocks_by_class(string $html, string $className): array {
	$blocks = [];
	$pattern = '/<div\b[^>]*class="[^"]*\b'.preg_quote($className, '/').'\b[^"]*"[^>]*>/i';
	if (!preg_match_all($pattern, $html, $matches, PREG_OFFSET_CAPTURE)) {
		return [];
	}
	foreach ($matches[0] as $match) {
		$start = (int)$match[1];
		$offset = $start;
		$depth = 0;
		while (preg_match('/<\/?div\b[^>]*>/i', $html, $tagMatch, PREG_OFFSET_CAPTURE, $offset)) {
			$tag = $tagMatch[0][0];
			$tagStart = (int)$tagMatch[0][1];
			$offset = $tagStart + strlen($tag);
			if (str_starts_with(strtolower($tag), '</div')) {
				$depth--;
				if ($depth === 0) {
					$blocks[] = ['start' => $start, 'end' => $offset, 'html' => substr($html, $start, $offset - $start)];
					break;
				}
			} else {
				$depth++;
			}
		}
	}
	return $blocks;
}

function cms_replace_div_blocks_by_class(string $html, string $className, string $replacement): string {
	$blocks = cms_div_blocks_by_class($html, $className);
	for ($index = count($blocks) - 1; $index >= 0; $index--) {
		$block = $blocks[$index];
		$html = substr($html, 0, $block['start']).$replacement.substr($html, $block['end']);
	}
	return $html;
}

function cms_unwrap_text_columns(string $html): string {
	return preg_replace('/<div\b[^>]*class="[^"]*\btexto-columna\b[^"]*"[^>]*>([\s\S]*?)<\/div>/i', '$1', $html) ?? $html;
}

function cms_route_line(array $parts): string {
	return rtrim(implode(' | ', array_map(fn($part): string => trim((string)$part), $parts)), " |");
}

function cms_style_from_media_markup(string $markup, string $tag = ''): string {
	$classes = $tag !== '' ? cms_attr($tag, 'class') : '';
	if (preg_match_all('/\bclass\s*=\s*(["\'])(.*?)\1/is', $markup, $classMatches)) {
		$classes .= ' '.implode(' ', $classMatches[2]);
	}
	return cms_style_class(
		cms_style_from_classes($classes),
		$tag !== '' ? (int)cms_attr($tag, 'width') : 0,
		$tag !== '' ? (int)cms_attr($tag, 'height') : 0
	);
}

function cms_live_photo_style_from_classes(string $classes): string {
	$normalized = mb_strtolower(str_replace(['_', ' '], ['-', '-'], $classes), 'UTF-8');
	if (str_contains($normalized, 'live-photo-horizontal') || str_contains($normalized, '2:1')) {
		return 'live-photo-horizontal';
	}
	return cms_style_class(cms_style_from_classes($classes));
}

function cms_live_photo_style_class(string $style): string {
	$normalized = mb_strtolower(str_replace(['_', ' '], ['-', '-'], trim($style)), 'UTF-8');
	if ($normalized === '') {
		return '';
	}
	if (str_contains($normalized, 'live-photo-horizontal') || str_contains($normalized, '2:1')) {
		return 'live-photo-horizontal';
	}
	return cms_style_class($normalized);
}

function cms_text_align_from_section(string $sectionClass, array $textColumns): string {
	$probe = $sectionClass.' '.($textColumns[0]['tag'] ?? '');
	if (preg_match('/text-align\s*:\s*(center|right|left)/i', $probe, $match)) {
		return strtolower($match[1]);
	}
	if (str_contains($sectionClass, 'espacio-retratos') || str_contains($sectionClass, 'texto-centrado')) {
		return 'center';
	}
	return 'left';
}

function cms_extract_block_media(string $sectionHtml): array {
	$mediaStyle = '';
	$routes = [];
	$captions = [];
	$galleryBlocks = cms_div_blocks_by_class($sectionHtml, 'galeria-imagenes');
	if ($galleryBlocks) {
		$mediaStyle = 'galeria-imagenes';
		foreach ($galleryBlocks as $gallery) {
			if (preg_match_all('/<figure\b[^>]*>([\s\S]*?)<\/figure>/i', $gallery['html'], $figures, PREG_SET_ORDER)) {
				foreach ($figures as $figure) {
					$figureHtml = $figure[0];
					if (preg_match('/<img\b[^>]*>/i', $figureHtml, $imgMatch)) {
						$tag = $imgMatch[0];
						$routes[] = cms_route_line([
							'image',
							cms_attr($tag, 'src'),
							cms_attr($tag, 'alt'),
							cms_style_from_media_markup($figureHtml, $tag),
							cms_attr($tag, 'width'),
							cms_attr($tag, 'height'),
						]);
						$captions[] = cms_extract_figcaption_html($figureHtml);
						continue;
					}
					if (preg_match('/<source\b[^>]*\bsrc="([^"]+)"/i', $figureHtml, $sourceMatch)) {
						$videoTag = preg_match('/<video\b[^>]*>/i', $figureHtml, $videoTagMatch) ? $videoTagMatch[0] : '';
						$routes[] = cms_route_line([
							'video',
							html_entity_decode($sourceMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
							$videoTag !== '' ? cms_attr($videoTag, 'aria-label') : '',
							cms_style_from_media_markup($figureHtml, $videoTag),
							$videoTag !== '' ? cms_attr($videoTag, 'width') : '',
							$videoTag !== '' ? cms_attr($videoTag, 'height') : '',
							$videoTag !== '' ? cms_attr($videoTag, 'poster') : '',
						]);
						$captions[] = cms_extract_figcaption_html($figureHtml);
					}
				}
			}
		}
		return ['style' => $mediaStyle, 'routes' => implode("\n", $routes), 'captions' => implode("\n---\n", $captions)];
	}
	if (preg_match_all('/<figure\b[^>]*>([\s\S]*?)<\/figure>/i', $sectionHtml, $figures, PREG_SET_ORDER)) {
		foreach ($figures as $figure) {
			$figureHtml = $figure[0];
			$sourceSrc = '';
			if (preg_match('/<source\b[^>]*\bsrc="([^"]+)"/i', $figureHtml, $sourceProbe)) {
				$sourceSrc = html_entity_decode($sourceProbe[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
			}
			$isLive = str_contains(cms_attr($figureHtml, 'class').' '.$figureHtml, 'live-photo') && $sourceSrc !== '' && cms_media_type_from_src($sourceSrc) === 'video';
			if ($isLive) {
				$mediaStyle = 'live-photo';
				$image = '';
				$video = '';
				$alt = '';
				if (preg_match('/<img\b[^>]*>/i', $figureHtml, $imgMatch)) {
					$image = cms_attr($imgMatch[0], 'src');
					$alt = cms_attr($imgMatch[0], 'alt');
				}
				if (preg_match('/<source\b[^>]*\bsrc="([^"]+)"/i', $figureHtml, $sourceMatch)) {
					$video = html_entity_decode($sourceMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				}
				$liveStyle = '';
				if (preg_match('/<div\b[^>]*class="([^"]*\blive-photo\b[^"]*)"[^>]*>/i', $figureHtml, $containerMatch)) {
					$liveStyle = cms_live_photo_style_from_classes($containerMatch[1]);
				}
				$imgTag = preg_match('/<img\b[^>]*>/i', $figureHtml, $imgMatch) ? $imgMatch[0] : '';
				$routes[] = cms_route_line([
					'live-photo',
					$image,
					$video,
					$alt,
					$liveStyle,
					$imgTag !== '' ? cms_attr($imgTag, 'width') : '',
					$imgTag !== '' ? cms_attr($imgTag, 'height') : '',
				]);
			} elseif (preg_match('/<img\b[^>]*>/i', $figureHtml, $imgMatch)) {
				$tag = $imgMatch[0];
				$mediaStyle = $mediaStyle ?: 'simple';
				$routes[] = cms_route_line([
					'image',
					cms_attr($tag, 'src'),
					cms_attr($tag, 'alt'),
					cms_style_from_media_markup($figureHtml, $tag),
					cms_attr($tag, 'width'),
					cms_attr($tag, 'height'),
				]);
			} elseif (preg_match('/<source\b[^>]*\bsrc="([^"]+)"/i', $figureHtml, $sourceMatch)) {
				$mediaStyle = $mediaStyle ?: 'simple';
				$videoTag = preg_match('/<video\b[^>]*>/i', $figureHtml, $videoTagMatch) ? $videoTagMatch[0] : '';
				$poster = $videoTag !== '' ? cms_attr($videoTag, 'poster') : '';
				$routes[] = cms_route_line([
					'video',
					html_entity_decode($sourceMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
					'',
					cms_style_from_media_markup($figureHtml, $videoTag),
					$videoTag !== '' ? cms_attr($videoTag, 'width') : '',
					$videoTag !== '' ? cms_attr($videoTag, 'height') : '',
					$poster,
				]);
			}
			$captions[] = cms_extract_figcaption_html($figureHtml);
		}
	}
	return ['style' => $mediaStyle, 'routes' => implode("\n", $routes), 'captions' => implode("\n---\n", $captions)];
}

function cms_remove_media_from_section(string $html): string {
	$html = preg_replace('/<figure\b[\s\S]*?<\/figure>/i', '', $html) ?? $html;
	$html = cms_replace_div_blocks_by_class($html, 'galeria-imagenes', '');
	return trim($html);
}

function cms_section_html_with_media_markers(string $html): string {
	$index = 0;
	$html = cms_replace_div_blocks_by_class($html, 'galeria-imagenes', "\n{{galería}}\n");
	$html = preg_replace_callback('/<figure\b[\s\S]*?<\/figure>/i', function () use (&$index): string {
		$index++;
		return "\n{{media:".$index."}}\n";
	}, $html) ?? $html;
	return trim(cms_unwrap_text_columns($html));
}

function cms_has_media_markers(string $html): bool {
	return preg_match('/\{\{(?:media:\d+|galería|galeria)\}\}/iu', $html) === 1;
}

function cms_strip_media_markers(string $html): string {
	return preg_replace('/\{\{(?:media:\d+|galería|galeria)\}\}/iu', '', $html) ?? $html;
}

function cms_extract_section_blocks(string $html): array {
	$content = cms_extract_content_html($html);
	$blocks = [];
	if (preg_match_all('/<section\b([^>]*)>([\s\S]*?)<\/section>/i', $content, $sections, PREG_SET_ORDER)) {
		foreach ($sections as $section) {
			$sectionTag = '<section'.$section[1].'>';
			$class = cms_attr($sectionTag, 'class');
			$sectionHtml = $section[2];
			$layout = 'texto';
			$side = 'imagen-derecha';
			if (str_contains($class, 'imagen-y-texto')) {
				$layout = 'imagen-y-texto';
				$side = str_contains($class, 'imagen-izquierda') ? 'imagen-izquierda' : 'imagen-derecha';
			} elseif (str_contains($class, 'columnas-texto')) {
				$layout = 'columnas-texto';
			}
			$textColumns = cms_inner_html_from_tag($sectionHtml, 'div', '/\btexto-columna\b/');
			$textOne = $layout === 'texto' ? cms_section_html_with_media_markers($sectionHtml) : ($textColumns[0]['html'] ?? cms_section_html_with_media_markers($sectionHtml));
			$textTwo = $textColumns[1]['html'] ?? '';
			$subtitle = cms_first_h2_from_html($textOne);
			if ($subtitle === '' && $textTwo !== '') {
				$subtitle = cms_first_h2_from_html($textTwo);
			}
			$media = cms_extract_block_media($sectionHtml);
			$align = cms_text_align_from_section($class, $textColumns);
			$blocks[] = [
				'layout' => $layout,
				'side' => $side,
				'subtitle' => $subtitle,
				'align' => $align,
				'html' => trim($textOne),
				'html2' => trim($textTwo),
				'mediaStyle' => $media['style'],
				'mediaRoutes' => $media['routes'],
				'mediaCaptions' => $media['captions'],
			];
		}
	}
	if (!$blocks) {
		$blocks[] = cms_default_block(cms_extract_text($html), cms_media_lines(cms_extract_media_items($html)));
	}
	return $blocks;
}

function cms_default_block(string $text = '', string $media = ''): array {
	return [
		'layout' => 'texto',
		'side' => 'imagen-derecha',
		'subtitle' => '',
		'align' => 'left',
		'html' => cms_text_to_html($text),
		'html2' => '',
		'mediaStyle' => $media !== '' ? 'simple' : '',
		'mediaRoutes' => $media,
		'mediaCaptions' => '',
	];
}

function cms_media_style_options(): array {
	return [
		'' => 'Auto por dimensiones',
		'imagen-horizontal' => '.imagen-horizontal - 16:9',
		'imagen-horizontal-4-3' => '.imagen-horizontal-4-3 - 4:3',
		'imagen-panoramica' => '.imagen-panoramica - 2:1',
		'imagen-vertical' => '.imagen-vertical - 3:4 contenida',
		'imagen-vertical-4-3' => '.imagen-vertical-4-3 - 3:4',
		'imagen-vertical-9-16' => '.imagen-vertical-9-16 - 9:16',
		'imagen-cuadrada' => '.imagen-cuadrada - 1:1',
		'live-photo-horizontal' => '.live-photo-horizontal - 2:1',
	];
}

function cms_media_form_default_item(): array {
	return [
		'type' => 'image',
		'route' => '',
		'video' => '',
		'poster' => '',
		'style' => '',
		'width' => '',
		'height' => '',
		'alt' => '',
		'caption' => '',
	];
}

function cms_normalize_media_form_type(string $type): string {
	$type = mb_strtolower(str_replace(['_', ' '], ['-', '-'], trim($type)), 'UTF-8');
	return match ($type) {
		'video' => 'video',
		'live', 'live-photo', 'livephoto' => 'live-photo',
		default => 'image',
	};
}

function cms_normalize_media_form_style(string $style, string $type = 'image', int $width = 0, int $height = 0): string {
	$style = trim($style);
	if ($style === '') {
		return '';
	}
	if ($type === 'live-photo') {
		return cms_live_photo_style_class($style);
	}
	return cms_style_class($style, $width, $height);
}

function cms_normalize_media_form_item(array $item): ?array {
	$type = cms_normalize_media_form_type((string)($item['type'] ?? 'image'));
	$route = trim((string)($item['route'] ?? ''));
	$video = trim((string)($item['video'] ?? ''));
	$poster = trim((string)($item['poster'] ?? ''));
	$width = max(0, (int)($item['width'] ?? 0));
	$height = max(0, (int)($item['height'] ?? 0));
	$style = cms_normalize_media_form_style((string)($item['style'] ?? ''), $type, $width, $height);

	if ($type !== 'live-photo' && $route === '') {
		return null;
	}
	if ($type === 'live-photo' && $route === '' && $video === '') {
		return null;
	}

	return [
		'type' => $type,
		'route' => $route,
		'video' => $video,
		'poster' => $poster,
		'style' => $style,
		'width' => $width,
		'height' => $height,
		'alt' => str_replace('|', '/', cms_normalize_space((string)($item['alt'] ?? ''))),
		'caption' => trim((string)($item['caption'] ?? '')),
	];
}

function cms_normalized_media_form_items(mixed $items): array {
	if (!is_array($items)) {
		return [];
	}
	$normalized = [];
	foreach ($items as $item) {
		if (!is_array($item)) {
			continue;
		}
		$media = cms_normalize_media_form_item($item);
		if ($media !== null) {
			$normalized[] = $media;
		}
	}
	return $normalized;
}

function cms_media_routes_from_form_items(mixed $items): string {
	$lines = [];
	foreach (cms_normalized_media_form_items($items) as $item) {
		$width = (int)$item['width'];
		$height = (int)$item['height'];
		if ($item['type'] === 'live-photo') {
			$lines[] = cms_route_line([
				'live-photo',
				$item['route'],
				$item['video'],
				$item['alt'],
				$item['style'],
				$width > 0 ? (string)$width : '',
				$height > 0 ? (string)$height : '',
			]);
			continue;
		}
		if ($item['type'] === 'video') {
			$lines[] = cms_route_line([
				'video',
				$item['route'],
				$item['alt'],
				$item['style'],
				$width > 0 ? (string)$width : '',
				$height > 0 ? (string)$height : '',
				$item['poster'],
			]);
			continue;
		}
		$lines[] = cms_route_line([
			'image',
			$item['route'],
			$item['alt'],
			$item['style'],
			$width > 0 ? (string)$width : '',
			$height > 0 ? (string)$height : '',
		]);
	}
	return implode("\n", $lines);
}

function cms_media_captions_from_form_items(mixed $items): string {
	$captions = [];
	foreach (cms_normalized_media_form_items($items) as $item) {
		$captions[] = trim((string)$item['caption']);
	}
	return implode("\n---\n", $captions);
}

function cms_media_form_items_from_block(array $block): array {
	$items = [];
	foreach (cms_parse_block_media_routes($block) as $item) {
		$type = match ((string)($item['kind'] ?? 'image')) {
			'video' => 'video',
			'live' => 'live-photo',
			default => 'image',
		};
		$items[] = [
			'type' => $type,
			'route' => $type === 'live-photo' ? (string)($item['image'] ?? '') : (string)($item['url'] ?? ''),
			'video' => $type === 'live-photo' ? (string)($item['video'] ?? '') : '',
			'poster' => $type === 'video' ? (string)($item['poster'] ?? '') : '',
			'style' => (string)($item['style'] ?? ''),
			'width' => (int)($item['width'] ?? 0) > 0 ? (string)(int)$item['width'] : '',
			'height' => (int)($item['height'] ?? 0) > 0 ? (string)(int)$item['height'] : '',
			'alt' => (string)($item['alt'] ?? ''),
			'caption' => (string)($item['caption'] ?? ''),
		];
	}
	return $items ?: [cms_media_form_default_item()];
}

function cms_blocks_from_post(array $post): array {
	$blocks = [];
	$rawBlocks = $post['blocks'] ?? [];
	if (!is_array($rawBlocks)) {
		return [cms_default_block()];
	}
	foreach ($rawBlocks as $block) {
		if (!is_array($block)) {
			continue;
		}
		$layout = (string)($block['layout'] ?? 'texto');
		if (!in_array($layout, ['imagen-y-texto', 'columnas-texto', 'texto'], true)) {
			$layout = 'texto';
		}
		$side = (string)($block['side'] ?? 'imagen-derecha');
		if (!in_array($side, ['imagen-izquierda', 'imagen-derecha'], true)) {
			$side = 'imagen-derecha';
		}
		$align = (string)($block['align'] ?? 'left');
		if (!in_array($align, ['left', 'right', 'center'], true)) {
			$align = 'left';
		}
		$mediaStyle = (string)($block['mediaStyle'] ?? '');
		if (!in_array($mediaStyle, ['', 'simple', 'live-photo', 'galeria-imagenes'], true)) {
			$mediaStyle = '';
		}
		if (array_key_exists('mediaItems', $block)) {
			$mediaRoutes = cms_media_routes_from_form_items($block['mediaItems']);
			$mediaCaptions = cms_media_captions_from_form_items($block['mediaItems']);
		} else {
			$mediaRoutes = trim((string)($block['mediaRoutes'] ?? ''));
			$mediaCaptions = trim((string)($block['mediaCaptions'] ?? ''));
		}
		$blocks[] = [
			'layout' => $layout,
			'side' => $side,
			'subtitle' => cms_normalize_space((string)($block['subtitle'] ?? '')),
			'align' => $align,
			'html' => cms_allowed_html((string)($block['html'] ?? '')),
			'html2' => cms_allowed_html((string)($block['html2'] ?? '')),
			'mediaStyle' => $mediaStyle,
			'mediaRoutes' => $mediaRoutes,
			'mediaCaptions' => $mediaCaptions,
		];
	}
	return $blocks ?: [cms_default_block()];
}

function cms_block_image_urls(array $blocks): array {
	$urls = [];
	foreach ($blocks as $block) {
		foreach (cms_parse_block_media_routes($block) as $item) {
			if (($item['kind'] ?? '') === 'live' && !empty($item['image'])) {
				$image = cms_preview_image_src((string)$item['image']);
				if ($image !== '') {
					$urls[] = $image;
				}
				continue;
			}
			if (($item['kind'] ?? '') === 'image' && !empty($item['url'])) {
				$image = cms_preview_image_src((string)$item['url']);
				if ($image !== '') {
					$urls[] = $image;
				}
				continue;
			}
			if (($item['kind'] ?? '') === 'video' && !empty($item['poster'])) {
				$image = cms_preview_image_src((string)$item['poster']);
				if ($image !== '') {
					$urls[] = $image;
				}
			}
		}
	}
	return array_values(array_unique($urls));
}

function cms_parse_block_media_routes(array $block): array {
	$items = [];
	$captions = cms_split_captions((string)($block['mediaCaptions'] ?? ''));
	$style = (string)($block['mediaStyle'] ?? '');
	$lines = preg_split('/\R/u', trim((string)($block['mediaRoutes'] ?? ''))) ?: [];
	foreach ($lines as $index => $line) {
		$line = trim($line);
		if ($line === '') {
			continue;
		}
		$parts = array_map('trim', explode('|', $line));
		$type = cms_normalize_media_form_type((string)($parts[0] ?? ''));
		$hasExplicitType = in_array(mb_strtolower(str_replace(['_', ' '], ['-', '-'], (string)($parts[0] ?? '')), 'UTF-8'), ['image', 'imagen', 'img', 'video', 'live', 'live-photo', 'livephoto'], true);
		if ($hasExplicitType && $type === 'live-photo') {
			$width = (int)($parts[5] ?? 0);
			$height = (int)($parts[6] ?? 0);
			$items[] = [
				'kind' => 'live',
				'image' => $parts[1] ?? '',
				'video' => $parts[2] ?? '',
				'alt' => $parts[3] ?? '',
				'style' => cms_live_photo_style_class($parts[4] ?? ''),
				'width' => $width,
				'height' => $height,
				'caption' => $captions[$index] ?? '',
			];
			continue;
		}
		if ($hasExplicitType) {
			$url = $parts[1] ?? '';
			$legacyTypedOrder = preg_match('/^\d+$/', $parts[3] ?? '') === 1 && preg_match('/^\d+$/', $parts[4] ?? '') === 1;
			$styleValue = $legacyTypedOrder ? ($parts[5] ?? '') : ($parts[3] ?? '');
			$width = (int)($legacyTypedOrder ? ($parts[3] ?? 0) : ($parts[4] ?? 0));
			$height = (int)($legacyTypedOrder ? ($parts[4] ?? 0) : ($parts[5] ?? 0));
			$items[] = [
				'kind' => $type === 'video' ? 'video' : 'image',
				'url' => $url,
				'alt' => $parts[2] ?? '',
				'style' => cms_style_class($styleValue, $width, $height),
				'width' => $width,
				'height' => $height,
				'poster' => $type === 'video' && !$legacyTypedOrder ? ($parts[6] ?? '') : '',
				'caption' => $captions[$index] ?? ($legacyTypedOrder ? ($parts[6] ?? '') : ''),
			];
			continue;
		}
		if ($style === 'live-photo') {
			$legacyLiveVideo = $parts[1] ?? '';
			if ($legacyLiveVideo === '' || cms_media_type_from_src($legacyLiveVideo) !== 'video') {
				$url = $parts[0] ?? '';
				$kind = cms_media_type_from_src($url);
				$width = (int)($parts[3] ?? 0);
				$height = (int)($parts[4] ?? 0);
				$items[] = [
					'kind' => $kind,
					'url' => $url,
					'alt' => $parts[1] ?? '',
					'style' => cms_style_class($parts[2] ?? '', $width, $height),
					'width' => $width,
					'height' => $height,
					'poster' => $kind === 'video' ? ($parts[5] ?? '') : '',
					'caption' => $captions[$index] ?? '',
				];
				continue;
			}
			$items[] = [
				'kind' => 'live',
				'image' => $parts[0] ?? '',
				'video' => $parts[1] ?? '',
				'alt' => $parts[2] ?? '',
				'style' => cms_live_photo_style_class($parts[3] ?? ''),
				'width' => 0,
				'height' => 0,
				'caption' => $captions[$index] ?? '',
			];
			continue;
		}
		$url = $parts[0] ?? '';
		$items[] = [
			'kind' => cms_media_type_from_src($url),
			'url' => $url,
			'alt' => $parts[1] ?? '',
			'style' => cms_style_class($parts[2] ?? '', (int)($parts[3] ?? 0), (int)($parts[4] ?? 0)),
			'width' => (int)($parts[3] ?? 0),
			'height' => (int)($parts[4] ?? 0),
			'poster' => $parts[5] ?? '',
			'caption' => $captions[$index] ?? '',
		];
	}
	return $items;
}

function cms_render_figcaption(string $caption, string $indent): string {
	$caption = cms_allowed_caption_html($caption);
	if ($caption === '') {
		return '';
	}
	return "\n".$indent.'<figcaption>'.$caption.'</figcaption>';
}

function cms_video_type(string $src): string {
	$ext = strtolower(pathinfo(parse_url($src, PHP_URL_PATH) ?: '', PATHINFO_EXTENSION));
	return match ($ext) {
		'webm' => 'video/webm',
		'mov' => 'video/quicktime',
		default => 'video/mp4',
	};
}

function cms_render_gallery_media(array $block, string $title, string $indent = "\t\t\t\t", int $targetIndex = 0): string {
	$items = cms_parse_block_media_routes($block);
	if (!$items) {
		return '';
	}
	$html = $indent."<div class=\"galeria-imagenes\" data-lightbox-gallery>\n";
	$rendered = false;
	foreach ($items as $itemIndex => $item) {
		if ($targetIndex > 0 && $itemIndex + 1 !== $targetIndex) {
			continue;
		}
		if (empty($item['url'])) {
			continue;
		}
		$style = cms_style_class((string)($item['style'] ?? ''), (int)($item['width'] ?? 0), (int)($item['height'] ?? 0));
		$size = '';
		if (!empty($item['width'])) {
			$size .= ' width="'.(int)$item['width'].'"';
		}
		if (!empty($item['height'])) {
			$size .= ' height="'.(int)$item['height'].'"';
		}
		if (($item['kind'] ?? '') === 'video') {
			$poster = trim((string)($item['poster'] ?? ''));
			$posterAttr = $poster !== '' ? ' poster="'.cms_h($poster).'"' : '';
			$label = (string)($item['alt'] ?: $title);
			$html .= $indent."\t<figure>\n".
				$indent."\t\t<div class=\"contenedor-imagen ".cms_h($style ?: 'imagen-horizontal')."\">\n".
				$indent."\t\t\t<video class=\"".cms_h($style ?: 'imagen-horizontal')."\" controls preload=\"metadata\" playsinline aria-label=\"".cms_h($label)."\"".$size.$posterAttr.">\n".
				$indent."\t\t\t\t<source src=\"".cms_h($item['url'])."\" type=\"".cms_h(cms_video_type((string)$item['url']))."\">\n".
				$indent."\t\t\t</video>\n".
				$indent."\t\t</div>".
				cms_render_figcaption((string)($item['caption'] ?? ''), $indent."\t\t")."\n".
				$indent."\t</figure>\n";
			$rendered = true;
			continue;
		}
		$class = $style !== '' ? ' class="'.cms_h($style).'"' : '';
		$html .= $indent."\t<figure>\n".
			$indent."\t\t<a href=\"".cms_h($item['url'])."\" class=\"enlace-galeria\"><img".$class." src=\"".cms_h($item['url'])."\" sizes=\"(max-width:960px) 90vw, calc(90% - 300px)\" alt=\"".cms_h($item['alt'] ?: $title)."\"".$size." loading=\"lazy\"></a>".
			cms_render_figcaption((string)($item['caption'] ?? ''), $indent."\t\t")."\n".
			$indent."\t</figure>\n";
		$rendered = true;
	}
	if (!$rendered) {
		return '';
	}
	return rtrim($html)."\n".$indent.'</div>';
}

function cms_render_block_media(array $block, string $title, string $indent = "\t\t\t\t", int $targetIndex = 0): string {
	$items = cms_parse_block_media_routes($block);
	if (!$items) {
		return '';
	}
	$mediaStyle = (string)($block['mediaStyle'] ?? '');
	if ($mediaStyle === 'galeria-imagenes') {
		return cms_render_gallery_media($block, $title, $indent, $targetIndex);
	}
	$figures = [];
	foreach ($items as $itemIndex => $item) {
		if ($targetIndex > 0 && $itemIndex + 1 !== $targetIndex) {
			continue;
		}
		if (($item['kind'] ?? '') === 'live') {
			$image = (string)($item['image'] ?? '');
			if ($image === '') {
				continue;
			}
			$video = (string)($item['video'] ?? '');
			$liveStyle = cms_live_photo_style_class((string)($item['style'] ?? ''));
			$containerClass = trim('contenedor-imagen live-photo '.$liveStyle);
			$size = '';
			if (!empty($item['width'])) {
				$size .= ' width="'.(int)$item['width'].'"';
			}
			if (!empty($item['height'])) {
				$size .= ' height="'.(int)$item['height'].'"';
			}
			$figure = $indent."<figure class=\"imagen-columna\">\n".
				$indent."\t<div class=\"".cms_h($containerClass)."\">\n".
				$indent."\t\t<img src=\"".cms_h($image)."\" alt=\"".cms_h($item['alt'] ?: $title)."\" class=\"foto-estatica\"".$size." loading=\"lazy\">\n";
			if ($video !== '') {
				$figure .= $indent."\t\t<video loop muted playsinline class=\"live-video\">\n".
					$indent."\t\t\t<source src=\"".cms_h($video)."\" type=\"".cms_h(cms_video_type($video))."\">\n".
					$indent."\t\t</video>\n".
					$indent."\t\t<div class=\"live-badge\">LIVE</div>\n";
			}
			$figure .= $indent."\t</div>".cms_render_figcaption((string)($item['caption'] ?? ''), $indent."\t")."\n".$indent.'</figure>';
			$figures[] = $figure;
			continue;
		}
		if (($item['kind'] ?? '') === 'video') {
			$style = cms_style_class((string)($item['style'] ?? ''), (int)($item['width'] ?? 0), (int)($item['height'] ?? 0)) ?: 'imagen-horizontal';
			$poster = trim((string)($item['poster'] ?? ''));
			$posterAttr = $poster !== '' ? ' poster="'.cms_h($poster).'"' : '';
			$figures[] = $indent."<figure class=\"imagen-columna imagen-relato-horizontal\">\n".
				$indent."\t<div class=\"contenedor-imagen ".cms_h($style)."\">\n".
				$indent."\t\t<video controls preload=\"metadata\" playsinline".$posterAttr.">\n".
				$indent."\t\t\t<source src=\"".cms_h($item['url'])."\" type=\"".cms_h(cms_video_type((string)$item['url']))."\">\n".
				$indent."\t\t</video>\n".
				$indent."\t</div>".cms_render_figcaption((string)($item['caption'] ?? ''), $indent."\t")."\n".$indent.'</figure>';
			continue;
		}
		if (!empty($item['url'])) {
			$style = cms_style_class((string)($item['style'] ?? ''), (int)($item['width'] ?? 0), (int)($item['height'] ?? 0));
			$horizontal = $style === '' || str_contains($style, 'horizontal') || $style === 'imagen-cuadrada' || $style === 'imagen-panoramica';
			$figureClass = 'imagen-columna'.($horizontal ? ' imagen-relato-horizontal' : '');
			$size = '';
			if (!empty($item['width'])) {
				$size .= ' width="'.(int)$item['width'].'"';
			}
			if (!empty($item['height'])) {
				$size .= ' height="'.(int)$item['height'].'"';
			}
			$figures[] = $indent."<figure class=\"".cms_h($figureClass)."\">\n".
				$indent."\t<div class=\"contenedor-imagen ".cms_h($style)."\">\n".
				$indent."\t\t<img".($style !== '' ? ' class="'.cms_h($style).'"' : '')." src=\"".cms_h($item['url'])."\" sizes=\"(max-width:960px) 90vw, calc(90% - 300px)\" alt=\"".cms_h($item['alt'] ?: $title)."\"".$size." loading=\"lazy\">\n".
				$indent."\t</div>".cms_render_figcaption((string)($item['caption'] ?? ''), $indent."\t")."\n".$indent.'</figure>';
		}
	}
	return implode("\n", $figures);
}

function cms_text_column_attrs(string $align): string {
	return $align === 'left' ? 'class="texto-columna"' : 'class="texto-columna" style="text-align:'.cms_h($align).'"';
}

function cms_render_block_text(string $subtitle, string $html, string $align, string $indent): string {
	$parts = [];
	if ($subtitle !== '') {
		$parts[] = $indent.'<h2>'.cms_h($subtitle).'</h2>';
	}
	$html = cms_allowed_html($html);
	if ($html !== '') {
		foreach (explode("\n", $html) as $line) {
			$parts[] = $indent.$line;
		}
	}
	return implode("\n", $parts);
}

function cms_render_text_div(string $subtitle, string $html, string $align, string $indent): string {
	$text = cms_render_block_text($subtitle, $html, $align, $indent."\t");
	if (trim($text) === '') {
		return '';
	}
	return $indent.'<div '.cms_text_column_attrs($align).">\n".$text."\n".$indent.'</div>';
}

function cms_render_text_media_parts(array $block, string $title, string $align): array {
	$html = (string)($block['html'] ?? '');
	$subtitle = (string)($block['subtitle'] ?? '');
	$parts = [];
	$usedMedia = [];
	$subtitlePending = $subtitle;
	$mediaCount = count(cms_parse_block_media_routes($block));
	foreach (preg_split('/(\{\{(?:media:\d+|galería|galeria)\}\})/iu', $html, -1, PREG_SPLIT_DELIM_CAPTURE) ?: [] as $chunk) {
		if (preg_match('/^\{\{media:(\d+)\}\}$/', trim($chunk), $match)) {
			$mediaIndex = (int)$match[1];
			$media = cms_render_block_media($block, $title, "\t\t\t\t", $mediaIndex);
			if (trim($media) !== '') {
				$parts[] = $media;
				$usedMedia[$mediaIndex] = true;
			}
			continue;
		}
		if (preg_match('/^\{\{(?:galería|galeria)\}\}$/iu', trim($chunk))) {
			$media = cms_render_gallery_media($block, $title, "\t\t\t\t");
			if (trim($media) !== '') {
				$parts[] = $media;
				for ($mediaIndex = 1; $mediaIndex <= $mediaCount; $mediaIndex++) {
					$usedMedia[$mediaIndex] = true;
				}
			}
			continue;
		}
		if (trim($chunk) === '' && $subtitlePending === '') {
			continue;
		}
		$text = cms_render_text_div($subtitlePending, $chunk, $align, "\t\t\t\t");
		if ($text !== '') {
			$parts[] = $text;
		}
		$subtitlePending = '';
	}
	if (!$parts && $subtitlePending !== '') {
		$text = cms_render_text_div($subtitlePending, '', $align, "\t\t\t\t");
		if ($text !== '') {
			$parts[] = $text;
		}
	}
	for ($mediaIndex = 1; $mediaIndex <= $mediaCount; $mediaIndex++) {
		if (isset($usedMedia[$mediaIndex])) {
			continue;
		}
		$media = cms_render_block_media($block, $title, "\t\t\t\t", $mediaIndex);
		if (trim($media) !== '') {
			$parts[] = $media;
		}
	}
	return $parts;
}

function cms_render_blocks(array $blocks, string $title): string {
	$html = [];
	foreach ($blocks as $block) {
		$layout = (string)($block['layout'] ?? 'texto');
		$align = (string)($block['align'] ?? 'left');
		$subtitle = (string)($block['subtitle'] ?? '');
		$textOne = cms_render_block_text($subtitle, (string)($block['html'] ?? ''), $align, "\t\t\t\t\t");
		$textTwo = cms_render_block_text('', (string)($block['html2'] ?? ''), $align, "\t\t\t\t\t");
		$media = cms_render_block_media($block, $title, "\t\t\t\t");
		if ($layout === 'imagen-y-texto') {
			$side = (string)($block['side'] ?? 'imagen-derecha');
			if (!in_array($side, ['imagen-izquierda', 'imagen-derecha'], true)) {
				$side = 'imagen-derecha';
			}
			$html[] = "\t\t\t<section class=\"imagen-y-texto ".$side."\">\n".
				"\t\t\t\t<div ".cms_text_column_attrs($align).">\n".$textOne."\n\t\t\t\t</div>\n".
				$media."\n".
				"\t\t\t</section>";
			continue;
		}
		if ($layout === 'columnas-texto') {
			$html[] = "\t\t\t<section class=\"columnas-texto\">\n".
				"\t\t\t\t<div ".cms_text_column_attrs($align).">\n".$textOne."\n\t\t\t\t</div>\n".
				"\t\t\t\t<div ".cms_text_column_attrs($align).">\n".$textTwo."\n\t\t\t\t</div>\n".
				"\t\t\t</section>";
			continue;
		}
		$sectionParts = [];
		if (cms_has_media_markers((string)($block['html'] ?? ''))) {
			$sectionParts = cms_render_text_media_parts($block, $title, $align);
		} else {
			if (trim($textOne) !== '') {
				$sectionParts[] = "\t\t\t\t<div ".cms_text_column_attrs($align).">\n".$textOne."\n\t\t\t\t</div>";
			}
			if (trim($media) !== '') {
				$sectionParts[] = $media;
			}
		}
		$html[] = "\t\t\t<section class=\"texto\">\n".implode("\n", $sectionParts)."\n\t\t\t</section>";
	}
	return implode("\n", $html);
}

function cms_style_from_classes(string $classes): string {
	foreach ([
		'imagen-horizontal-4-3',
		'imagen-vertical-4-3',
		'imagen-vertical-9-16',
		'imagen-cuadrada',
		'imagen-panoramica',
		'imagen-horizontal',
		'imagen-vertical',
	] as $class) {
		if (str_contains($classes, $class)) {
			return $class;
		}
	}
	return '';
}

function cms_style_class(string $style, int $width = 0, int $height = 0): string {
	$style = trim($style);
	if ($style !== '') {
		$normalized = mb_strtolower($style, 'UTF-8');
		$normalized = str_replace(['_', ' '], ['-', '-'], $normalized);
		if (str_contains($normalized, 'imagen-horizontal-4-3') || (str_contains($normalized, 'horizontal') && str_contains($normalized, '4:3'))) {
			return 'imagen-horizontal-4-3';
		}
		if (str_contains($normalized, 'imagen-vertical-4-3') || (str_contains($normalized, 'vertical') && str_contains($normalized, '4:3'))) {
			return 'imagen-vertical-4-3';
		}
		if (str_contains($normalized, 'imagen-vertical-9-16') || str_contains($normalized, '9:16')) {
			return 'imagen-vertical-9-16';
		}
		if (str_contains($normalized, 'imagen-cuadrada') || str_contains($normalized, 'cuadrada') || str_contains($normalized, 'square') || str_contains($normalized, '1:1')) {
			return 'imagen-cuadrada';
		}
		if (str_contains($normalized, 'imagen-panoramica') || str_contains($normalized, 'panoramica') || str_contains($normalized, 'panorámica') || str_contains($normalized, '2:1')) {
			return 'imagen-panoramica';
		}
		if (str_contains($normalized, 'horizontal')) {
			return 'imagen-horizontal';
		}
		if (str_contains($normalized, 'vertical')) {
			return 'imagen-vertical';
		}
	}
	if ($width > 0 && $height > 0) {
		$ratio = $width / $height;
		if (abs($ratio - 1) < 0.04) {
			return 'imagen-cuadrada';
		}
		if ($width > $height && abs($ratio - 2) < 0.12) {
			return 'imagen-panoramica';
		}
		if ($width >= $height && abs($ratio - (4 / 3)) < 0.08) {
			return 'imagen-horizontal-4-3';
		}
		if ($height > $width && abs(($height / $width) - (4 / 3)) < 0.12) {
			return 'imagen-vertical-4-3';
		}
		if ($height > $width && abs(($width / $height) - (9 / 16)) < 0.08) {
			return 'imagen-vertical-9-16';
		}
		return $width >= $height ? 'imagen-horizontal' : 'imagen-vertical';
	}
	return '';
}

function cms_extract_media_items(string $html): array {
	$article = cms_article_html($html);
	$items = [];
	$seen = [];
	if (preg_match_all('/<figure\b[^>]*>([\s\S]*?)<\/figure>/i', $article, $figures, PREG_SET_ORDER)) {
		foreach ($figures as $figure) {
			$block = $figure[0];
			$caption = preg_match('/<figcaption\b[^>]*>([\s\S]*?)<\/figcaption>/i', $block, $cap) ? cms_html_text($cap[1]) : '';
			if (preg_match('/<img\b[^>]*>/i', $block, $imgMatch)) {
				$tag = $imgMatch[0];
				$src = cms_attr($tag, 'src');
				if ($src === '' || isset($seen[$src]) || str_contains($src, '/img/imagen-rota.png') || str_contains($src, '/portada.webp')) {
					continue;
				}
				$seen[$src] = true;
				$classes = cms_attr($tag, 'class');
				if (preg_match_all('/\bclass\s*=\s*(["\'])(.*?)\1/is', $block, $classMatches)) {
					$classes .= ' '.implode(' ', $classMatches[2]);
				}
				$width = (int)cms_attr($tag, 'width');
				$height = (int)cms_attr($tag, 'height');
				$items[] = [
					'type' => 'image',
					'src' => $src,
					'alt' => cms_attr($tag, 'alt'),
					'width' => $width,
					'height' => $height,
					'style' => cms_style_class(cms_style_from_classes($classes), $width, $height),
					'caption' => $caption,
				];
				continue;
			}
			if (preg_match('/<video\b[^>]*>/i', $block, $videoTagMatch) && preg_match('/<source\b[^>]*\bsrc="([^"]+)"/i', $block, $videoMatch)) {
				$tag = $videoTagMatch[0];
				$src = html_entity_decode($videoMatch[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
				if ($src === '' || isset($seen[$src])) {
					continue;
				}
				$seen[$src] = true;
				$classes = cms_attr($tag, 'class');
				if (preg_match_all('/\bclass\s*=\s*(["\'])(.*?)\1/is', $block, $classMatches)) {
					$classes .= ' '.implode(' ', $classMatches[2]);
				}
				$width = (int)cms_attr($tag, 'width');
				$height = (int)cms_attr($tag, 'height');
				$items[] = [
					'type' => 'video',
					'src' => $src,
					'alt' => '',
					'width' => $width,
					'height' => $height,
					'style' => cms_style_class(cms_style_from_classes($classes), $width, $height),
					'caption' => $caption,
					'poster' => cms_attr($tag, 'poster'),
				];
			}
		}
	}
	if (!$items && preg_match_all('/<img\b[^>]*>/i', $article, $images, PREG_SET_ORDER)) {
		foreach ($images as $image) {
			$tag = $image[0];
			$src = cms_attr($tag, 'src');
			if ($src === '' || isset($seen[$src]) || str_contains($src, '/img/imagen-rota.png') || str_contains($src, '/portada.webp')) {
				continue;
			}
			$seen[$src] = true;
			$width = (int)cms_attr($tag, 'width');
			$height = (int)cms_attr($tag, 'height');
			$items[] = [
				'type' => 'image',
				'src' => $src,
				'alt' => cms_attr($tag, 'alt'),
				'width' => $width,
				'height' => $height,
				'style' => cms_style_class(cms_style_from_classes(cms_attr($tag, 'class')), $width, $height),
				'caption' => '',
			];
		}
	}
	return $items;
}

function cms_extract_first_media(array $items): array {
	return $items[0] ?? ['type' => 'none', 'src' => ''];
}

function cms_media_provider(string $src): string {
	if ($src === '') {
		return 'Sin media';
	}
	$url = trim($src);
	if (str_starts_with($url, '//')) {
		$url = 'https:'.$url;
	}
	$host = parse_url($url, PHP_URL_HOST);
	if ($host === null || $host === false || $host === '' || str_starts_with($src, '/')) {
		return 'Local';
	}
	$host = strtolower((string)$host);
	if (in_array($host, ['danielestrella.com', 'www.danielestrella.com', 'blog.local', 'localhost', '127.0.0.1'], true)) {
		return 'Local';
	}
	if (str_contains($host, 'cloudinary.com')) {
		return 'Web - Cloudinary';
	}
	if ($host === 'iili.io' || str_ends_with($host, '.iili.io')) {
		return 'Web - iili.io';
	}
	if (str_contains($host, 'gfycat.com')) {
		return 'Web - Gfycat';
	}
	if (str_contains($host, 'googleusercontent.com')) {
		return 'Web - Google';
	}
	return 'Web - '.$host;
}

function cms_parse_entry_file(string $file, string $relative): ?array {
	$html = file_get_contents($file);
	if ($html === false) {
		return null;
	}
	[$dateIso, $dateText] = cms_extract_date($html);
	$dateKey = $dateIso !== '' ? $dateIso : $relative;
	$mediaItems = cms_extract_media_items($html);
	$firstMedia = cms_extract_first_media($mediaItems);
	return [
		'file' => $relative,
		'path' => dirname($relative),
		'url' => '/'.dirname($relative).'/',
		'title' => cms_extract_title($html),
		'summary' => cms_extract_summary($html),
		'dateIso' => $dateIso,
		'dateText' => $dateText,
		'text' => cms_extract_text($html),
		'mediaItems' => $mediaItems,
		'media' => $firstMedia,
		'metaImage' => cms_extract_meta_image($html),
		'previewImage' => cms_extract_preview_image($html),
		'provider' => cms_media_provider((string)($firstMedia['src'] ?? '')),
		'sort' => $dateKey,
		'html' => $html,
	];
}

function cms_scan_entries(): array {
	$entries = [];
	$archive = CMS_ROOT.'/archivo';
	if (!is_dir($archive)) {
		return [];
	}
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($archive, FilesystemIterator::SKIP_DOTS));
	foreach ($iterator as $file) {
		if (!$file->isFile() || $file->getFilename() !== 'index.html') {
			continue;
		}
		$relative = cms_relative_path($file->getPathname());
		if (!preg_match('#^archivo/\d{4}/\d{2}/[^/]+/index\.html$#', $relative)) {
			continue;
		}
		$entry = cms_parse_entry_file($file->getPathname(), $relative);
		if ($entry !== null) {
			$entries[] = $entry;
		}
	}
	usort($entries, fn(array $a, array $b): int => strcmp((string)$b['sort'], (string)$a['sort']));
	return $entries;
}

function cms_media_lines(array $items): string {
	$lines = [];
	foreach ($items as $item) {
		$parts = [
			$item['type'] ?? 'image',
			$item['src'] ?? '',
			str_replace('|', '/', (string)($item['alt'] ?? '')),
			(string)((int)($item['width'] ?? 0) ?: ''),
			(string)((int)($item['height'] ?? 0) ?: ''),
			$item['style'] ?? '',
			str_replace('|', '/', (string)($item['caption'] ?? '')),
		];
		$lines[] = rtrim(implode('|', $parts), '|');
	}
	return implode("\n", $lines);
}

function cms_media_type_from_src(string $src): string {
	return preg_match('/\.(mp4|webm|mov|m4v)(\?.*)?$/i', $src) ? 'video' : 'image';
}

function cms_preview_image_src(string $src): string {
	$src = trim($src);
	if ($src === '' || cms_media_type_from_src($src) === 'video') {
		return '';
	}
	return $src;
}

function cms_first_media_preview_src(array $mediaItems): string {
	foreach ($mediaItems as $item) {
		if (($item['type'] ?? 'image') === 'image') {
			$image = cms_preview_image_src((string)($item['src'] ?? ''));
			if ($image !== '') {
				return $image;
			}
		}
	}
	foreach ($mediaItems as $item) {
		if (($item['type'] ?? '') === 'video') {
			$image = cms_preview_image_src((string)($item['poster'] ?? ''));
			if ($image !== '') {
				return $image;
			}
		}
	}
	return '';
}

function cms_media_items_have_video(array $mediaItems): bool {
	foreach ($mediaItems as $item) {
		if (($item['type'] ?? '') === 'video' && trim((string)($item['src'] ?? '')) !== '') {
			return true;
		}
	}
	return false;
}

function cms_blocks_have_video(array $blocks): bool {
	foreach ($blocks as $block) {
		foreach (cms_parse_block_media_routes($block) as $item) {
			if (($item['kind'] ?? '') === 'video' && trim((string)($item['url'] ?? '')) !== '') {
				return true;
			}
		}
	}
	return false;
}

function cms_resolve_preview_image(string $explicit, array $blocks = [], array $mediaItems = []): string {
	$previewImage = cms_preview_image_src($explicit);
	if ($previewImage !== '') {
		return $previewImage;
	}
	foreach (cms_block_image_urls($blocks) as $image) {
		$previewImage = cms_preview_image_src((string)$image);
		if ($previewImage !== '') {
			return $previewImage;
		}
	}
	$previewImage = cms_first_media_preview_src($mediaItems);
	if ($previewImage !== '') {
		return $previewImage;
	}
	if (cms_blocks_have_video($blocks) || cms_media_items_have_video($mediaItems)) {
		return CMS_DEFAULT_PREVIEW_IMAGE;
	}
	return '';
}

function cms_parse_media_lines(string $text): array {
	$items = [];
	foreach (preg_split('/\R/u', trim($text)) ?: [] as $line) {
		$line = trim($line);
		if ($line === '') {
			continue;
		}
		$parts = array_map('trim', explode('|', $line));
		if (count($parts) === 1 || !in_array(strtolower($parts[0]), ['image', 'imagen', 'img', 'video'], true)) {
			array_unshift($parts, cms_media_type_from_src($parts[0] ?? ''));
		}
		$type = strtolower($parts[0] ?? 'image');
		$type = in_array($type, ['video'], true) ? 'video' : 'image';
		$src = $parts[1] ?? '';
		if ($src === '') {
			continue;
		}
		$width = (int)($parts[3] ?? 0);
		$height = (int)($parts[4] ?? 0);
		$items[] = [
			'type' => $type,
			'src' => $src,
			'alt' => $parts[2] ?? '',
			'width' => $width,
			'height' => $height,
			'style' => cms_style_class($parts[5] ?? '', $width, $height),
			'caption' => $parts[6] ?? '',
		];
	}
	return $items;
}

function cms_render_media_item(array $item, string $title): string {
	$type = $item['type'] ?? 'image';
	$src = trim((string)($item['src'] ?? ''));
	if ($src === '') {
		return '';
	}
	$caption = trim((string)($item['caption'] ?? ''));
	if ($type === 'video') {
		$width = (int)($item['width'] ?? 0);
		$height = (int)($item['height'] ?? 0);
		$style = cms_style_class((string)($item['style'] ?? ''), $width, $height) ?: 'imagen-horizontal';
		$wideFigure = !str_contains($style, 'vertical');
		$figureClass = 'imagen-columna'.($wideFigure ? ' imagen-relato-horizontal' : '');
		$videoAttrs = ' controls preload="metadata" playsinline';
		if ($width > 0) {
			$videoAttrs .= ' width="'.$width.'"';
		}
		if ($height > 0) {
			$videoAttrs .= ' height="'.$height.'"';
		}
		$captionHtml = $caption !== '' ? "\n\t\t\t\t\t<figcaption>".cms_linkify_text($caption).'</figcaption>' : '';
		return "\t\t\t\t<figure class=\"".cms_h($figureClass)."\">\n".
			"\t\t\t\t\t<div class=\"contenedor-imagen ".cms_h($style)."\">\n".
			"\t\t\t\t\t\t<video".$videoAttrs.">\n".
			"\t\t\t\t\t\t\t<source src=\"".cms_h($src)."\">\n".
			"\t\t\t\t\t\t</video>\n".
			"\t\t\t\t\t</div>".$captionHtml."\n".
			"\t\t\t\t</figure>";
	}
	$width = (int)($item['width'] ?? 0);
	$height = (int)($item['height'] ?? 0);
	$style = cms_style_class((string)($item['style'] ?? ''), $width, $height);
	$horizontal = $style === '' || str_contains($style, 'horizontal') || ($width > 0 && $height > 0 && $width >= $height);
	$figureClass = 'imagen-columna'.($horizontal ? ' imagen-relato-horizontal' : '');
	$containerClass = trim('contenedor-imagen '.$style);
	$imageClass = $style !== '' ? ' class="'.cms_h($style).'"' : '';
	$attrs = $imageClass.' src="'.cms_h($src).'"';
	$attrs .= ' sizes="(max-width:960px) 90vw, calc(90% - 300px)"';
	$attrs .= ' alt="'.cms_h((string)($item['alt'] ?? '') ?: $title).'"';
	if ($width > 0) {
		$attrs .= ' width="'.$width.'"';
	}
	if ($height > 0) {
		$attrs .= ' height="'.$height.'"';
	}
	$attrs .= ' loading="lazy"';
	$captionHtml = $caption !== '' ? "\n\t\t\t\t\t<figcaption>".cms_linkify_text($caption).'</figcaption>' : '';
	return "\t\t\t\t<figure class=\"".cms_h($figureClass)."\">\n".
		"\t\t\t\t\t<div class=\"".cms_h($containerClass)."\">\n".
		"\t\t\t\t\t\t<img".$attrs.">\n".
		"\t\t\t\t\t</div>".$captionHtml."\n".
		"\t\t\t\t</figure>";
}

function cms_absolute_media_url(string $src): string {
	if ($src === '') {
		return CMS_DOMAIN.'/portada.webp';
	}
	if (str_starts_with($src, 'http://') || str_starts_with($src, 'https://')) {
		return $src;
	}
	if (str_starts_with($src, '//')) {
		return 'https:'.$src;
	}
	return CMS_DOMAIN.'/'.ltrim($src, '/');
}

function cms_first_image_src(array $mediaItems): string {
	foreach ($mediaItems as $item) {
		if (($item['type'] ?? 'image') === 'image' && !empty($item['src'])) {
			$image = cms_preview_image_src((string)$item['src']);
			if ($image !== '') {
				return $image;
			}
		}
	}
	return '';
}

function cms_blocks_plain_text(array $blocks): string {
	$parts = [];
	foreach ($blocks as $block) {
		foreach (['subtitle', 'html', 'html2', 'mediaCaptions'] as $key) {
			$text = cms_html_text(cms_strip_media_markers((string)($block[$key] ?? '')));
			if ($text !== '') {
				$parts[] = $text;
			}
		}
	}
	return implode("\n\n", $parts);
}

function cms_ensure_lazy_images(string $html): string {
	return preg_replace_callback('/<img\b[^>]*>/i', function (array $match): string {
		$tag = preg_replace('/\s+loading\s*=\s*(["\']).*?\1/i', '', $match[0]) ?? $match[0];
		return preg_replace('/\s*\/?>$/', ' loading="lazy">', $tag, 1) ?? $tag;
	}, $html) ?? $html;
}

function cms_render_entry(array $data, string $entryPath): string {
	$title = cms_normalize_space((string)($data['title'] ?? ''));
	if ($title === '') {
		throw new RuntimeException('El título no puede quedar vacío.');
	}
	$date = $data['date'] instanceof DateTimeImmutable ? $data['date'] : cms_datetime_from_input((string)($data['date'] ?? ''));
	$iso = $date->format('c');
	$local = cms_local_info($iso);
	$blocks = is_array($data['blocks'] ?? null) ? $data['blocks'] : [];
	$text = $blocks ? cms_blocks_plain_text($blocks) : cms_normalize_space((string)($data['text'] ?? ''));
	$mediaItems = $data['mediaItems'] ?? [];
	$summary = cms_summary((string)($data['summary'] ?? ''), $text);
	$slug = basename($entryPath);
	$canonical = CMS_DOMAIN.'/'.$entryPath.'/';
	$previewImage = cms_resolve_preview_image((string)($data['previewImage'] ?? ''), $blocks, $mediaItems);
	$featured = cms_preview_image_src((string)($data['metaImage'] ?? ''));
	$featured = $featured !== '' ? cms_absolute_media_url($featured) : CMS_DOMAIN.CMS_DEFAULT_PREVIEW_IMAGE;
	if ($blocks) {
		$sectionHtml = cms_render_blocks($blocks, $title);
	} else {
		$mediaHtml = implode("\n", array_values(array_filter(array_map(fn(array $item): string => cms_render_media_item($item, $title), $mediaItems))));
		$textHtml = cms_text_to_html($text);
		$sectionParts = array_filter([$mediaHtml, $textHtml], fn(string $part): bool => trim($part) !== '');
		$sectionHtml = "\t\t\t<section class=\"texto-columna\">\n".implode("\n", $sectionParts)."\n\t\t\t</section>";
	}
	$logo = cms_logo_svg();
	$cssHref = cms_current_css_href();
	$jsSrc = cms_current_js_src();
	$previewMeta = $previewImage !== '' ? "\t<meta name=\"cms:preview_image\" content=\"".cms_h($previewImage)."\">\n" : '';
	$html = '<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>'.cms_h($title).' — Daniel Estrella</title>
	<meta name="description" content="'.cms_h($summary).'">
'.$previewMeta.'	<meta name="author" content="Daniel Estrella">
	<meta name="robots" content="index,follow,max-image-preview:large">
	<link rel="canonical" href="'.cms_h($canonical).'">
	<meta property="og:locale" content="es_MX">
	<meta property="og:site_name" content="Daniel Estrella">
	<meta property="og:type" content="article">
	<meta property="og:title" content="'.cms_h($title).' — Daniel Estrella">
	<meta property="og:description" content="'.cms_h($summary).'">
	<meta property="og:url" content="'.cms_h($canonical).'">
	<meta property="og:image" content="'.cms_h($featured).'">
	<meta property="article:published_time" content="'.cms_h($iso).'">
	<meta property="article:author" content="Daniel Estrella">
	<meta property="article:section" content="Archivo '.$local['year'].'">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="'.cms_h($title).' — Daniel Estrella">
	<meta name="twitter:description" content="'.cms_h($summary).'">
	<meta name="twitter:image" content="'.cms_h($featured).'">
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lavishly+Yours&amp;family=Victor+Mono:ital,wght@0,100..700;1,100..700&amp;display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://danielestrella.com/css/fuentes.min.css">
	<link rel="stylesheet" href="'.cms_h($cssHref).'">
</head>
<body>
	<header>
		<p><a href="/">'.$logo.' <strong>Daniel Estrella</strong></a></p>
		<p>Sitio personal y reflexiones ✨</p>
		<p class="estadisticas"><a href="/archivo/">🗄️ Archivo</a></p>
	</header>
	<main>
		<article class="pagina entrada-'.cms_h($slug).'">
			<h1>'.cms_linkify_text($title).'</h1>
			<p class="fecha-entrada"><time datetime="'.cms_h($iso).'">'.cms_h($local['dateText']).'</time></p>
'.$sectionHtml.'
		</article>
	</main>
	<footer class="pie-de-pagina">
		<p>✨ DanielEstrella.com — DEstrella.mx ✨</p>
		<p class="enlaces-al-pie"><a href="/">🦊 Inicio</a> <a href="/archivo/">🗄️ Archivo</a></p>
		<p>🌸 Hecho con 💖 y mucho ☕️</p>
	</footer>
	<script src="'.cms_h($jsSrc).'"></script>
</body>
</html>
';
	return cms_ensure_lazy_images($html);
}

function cms_entry_path(DateTimeImmutable $date, string $title, ?string $oldFile = null): array {
	$local = $date->setTimezone(new DateTimeZone(CMS_TIMEZONE));
	$year = $local->format('Y');
	$month = $local->format('m');
	$parentRel = 'archivo/'.$year.'/'.$month;
	$parentAbs = CMS_ROOT.'/'.$parentRel;
	$currentSlug = null;
	if ($oldFile !== null && preg_match('#^archivo/\d{4}/\d{2}/([^/]+)/index\.html$#', $oldFile, $match)) {
		$oldParent = dirname(dirname($oldFile));
		if ($oldParent === $parentRel) {
			$currentSlug = $match[1];
		}
	}
	$slug = cms_unique_slug($parentAbs, cms_slugify($title), $currentSlug);
	return [$parentRel.'/'.$slug, $slug];
}

function cms_save_entry(array $payload, ?string $oldFile = null): array {
	if (($payload['content_mode'] ?? '') === 'html' && $oldFile !== null) {
		return cms_save_structured_entry($payload, $oldFile);
	}
	$title = cms_normalize_space((string)($payload['title'] ?? ''));
	if ($title === '') {
		throw new RuntimeException('El título no puede quedar vacío.');
	}
	$date = cms_datetime_from_input((string)($payload['date'] ?? ''));
	$isBlockMode = ($payload['content_mode'] ?? '') === 'blocks' || isset($payload['blocks']);
	$blocks = $isBlockMode ? cms_blocks_from_post($payload) : [];
	$mediaItems = $isBlockMode ? [] : cms_parse_media_lines((string)($payload['media'] ?? ''));
	[$entryPath] = cms_entry_path($date, $title, $oldFile);
	$data = [
		'title' => $title,
		'summary' => (string)($payload['summary'] ?? ''),
		'date' => $date,
		'text' => (string)($payload['text'] ?? ''),
		'mediaItems' => $mediaItems,
		'blocks' => $blocks,
		'previewImage' => (string)($payload['preview_image'] ?? ''),
		'metaImage' => (string)($payload['meta_image'] ?? ''),
	];
	$html = cms_render_entry($data, $entryPath);
	$newDir = CMS_ROOT.'/'.$entryPath;
	$newFile = $newDir.'/index.html';
	$oldTitle = '';
	$oldEntryPath = null;
	$oldAbs = null;
	if ($oldFile !== null) {
		$oldAbs = cms_resolve_entry_file($oldFile);
		$oldHtml = (string)file_get_contents($oldAbs);
		$oldTitle = cms_extract_title($oldHtml);
		$oldEntryPath = dirname($oldFile);
	}
	if ($oldAbs !== null && dirname($oldAbs) !== $newDir) {
		if (is_dir($newDir)) {
			throw new RuntimeException('La ruta destino ya existe: '.cms_relative_path($newDir));
		}
		if (!is_dir(dirname($newDir))) {
			mkdir(dirname($newDir), 0755, true);
		}
		if (!rename(dirname($oldAbs), $newDir)) {
			throw new RuntimeException('No se pudo mover la carpeta de la entrada.');
		}
	} elseif (!is_dir($newDir)) {
		mkdir($newDir, 0755, true);
	}
	if (file_put_contents($newFile, $html) === false) {
		throw new RuntimeException('No se pudo escribir la entrada.');
	}
	if ($oldEntryPath !== null) {
		cms_update_references($oldEntryPath, $entryPath, $oldTitle, $title);
	}
	$totalPages = cms_rebuild_archive_and_sitemap();
	return [
		'file' => $entryPath.'/index.html',
		'path' => $entryPath,
		'url' => '/'.$entryPath.'/',
		'totalPages' => $totalPages,
	];
}

function cms_save_structured_entry(array $payload, string $oldFile): array {
	$title = cms_normalize_space((string)($payload['title'] ?? ''));
	if ($title === '') {
		throw new RuntimeException('El título no puede quedar vacío.');
	}
	$date = cms_datetime_from_input((string)($payload['date'] ?? ''));
	$contentHtml = trim((string)($payload['content_html'] ?? ''));
	if ($contentHtml === '') {
		throw new RuntimeException('El HTML de contenido no puede quedar vacío.');
	}
	$contentHtml = cms_ensure_lazy_images($contentHtml);
	$oldAbs = cms_resolve_entry_file($oldFile);
	$html = (string)file_get_contents($oldAbs);
	$oldTitle = cms_extract_title($html);
	$oldEntryPath = dirname($oldFile);
	[$newEntryPath, $newSlug] = cms_entry_path($date, $title, $oldFile);
	$newDir = CMS_ROOT.'/'.$newEntryPath;
	$newFile = $newDir.'/index.html';
	$iso = $date->format('c');
	$local = cms_local_info($iso);
	$summary = cms_summary((string)($payload['summary'] ?? ''), cms_html_text($contentHtml));
	$url = CMS_DOMAIN.'/'.$newEntryPath.'/';
	$titlePage = cms_h($title).' — Daniel Estrella';
	$titleAttr = cms_h($title.' — Daniel Estrella');
	$summaryAttr = cms_h($summary);
	$urlAttr = cms_h($url);
	$contentMediaItems = cms_extract_media_items($contentHtml);
	$previewImage = cms_resolve_preview_image((string)($payload['preview_image'] ?? cms_extract_preview_image($html)), [], $contentMediaItems);

	$html = preg_replace('/(<title>)[\s\S]*?(<\/title>)/', '$1'.$titlePage.'$2', $html, 1) ?? $html;
	$html = cms_replace_meta_content($html, 'name', 'description', $summaryAttr);
	$html = cms_replace_meta_content($html, 'property', 'og:title', $titleAttr);
	$html = cms_replace_meta_content($html, 'property', 'og:description', $summaryAttr);
	$html = cms_replace_meta_content($html, 'name', 'twitter:title', $titleAttr);
	$html = cms_replace_meta_content($html, 'name', 'twitter:description', $summaryAttr);
	$html = cms_replace_meta_content($html, 'property', 'article:published_time', cms_h($iso));
	$html = preg_replace_callback('/(<link\b[^>]*rel="canonical"[^>]*href=")[^"]*("[^>]*>)/i', fn(array $match): string => $match[1].$urlAttr.$match[2], $html, 1) ?? $html;
	$html = cms_replace_meta_content($html, 'property', 'og:url', $urlAttr);
	$html = cms_set_preview_meta($html, $previewImage);

	$articleOpen = '<article class="pagina entrada-'.cms_h($newSlug).'">';
	if (preg_match('/<article\b[^>]*>/i', $html, $match)) {
		$articleOpen = preg_replace_callback(
			'/(<article\b[^>]*class="[^"]*)entrada-[a-z0-9-]+([^"]*")/i',
			fn(array $classMatch): string => $classMatch[1].'entrada-'.cms_h($newSlug).$classMatch[2],
			$match[0],
			1
		) ?? $articleOpen;
	}
	$article = $articleOpen."\n".
		"\t\t\t<h1>".cms_linkify_text($title)."</h1>\n".
		"\t\t\t<p class=\"fecha-entrada\"><time datetime=\"".cms_h($iso)."\">".cms_h($local['dateText'])."</time></p>\n".
		$contentHtml."\n".
		"\t\t</article>";
	$html = preg_replace('/<article\b[\s\S]*?<\/article>/i', $article, $html, 1) ?? $html;
	$html = cms_ensure_lazy_images($html);

	if (dirname($oldAbs) !== $newDir) {
		if (is_dir($newDir)) {
			throw new RuntimeException('La ruta destino ya existe: '.cms_relative_path($newDir));
		}
		if (!is_dir(dirname($newDir))) {
			mkdir(dirname($newDir), 0755, true);
		}
		if (!rename(dirname($oldAbs), $newDir)) {
			throw new RuntimeException('No se pudo mover la carpeta de la entrada.');
		}
	} elseif (!is_dir($newDir)) {
		mkdir($newDir, 0755, true);
	}
	if (file_put_contents($newFile, $html) === false) {
		throw new RuntimeException('No se pudo escribir la entrada estructurada.');
	}
	cms_update_references($oldEntryPath, $newEntryPath, $oldTitle, $title);
	$totalPages = cms_rebuild_archive_and_sitemap();
	return [
		'file' => $newEntryPath.'/index.html',
		'path' => $newEntryPath,
		'url' => '/'.$newEntryPath.'/',
		'totalPages' => $totalPages,
	];
}

function cms_move_to_trash(string $relativeFile, string $reason = 'deleted'): string {
	$abs = cms_resolve_entry_file($relativeFile);
	$dir = dirname($abs);
	$trashBase = CMS_TRASH_DIR.'/'.$reason.'-'.date('Ymd-His');
	$dest = $trashBase.'/'.dirname($relativeFile);
	if (!is_dir(dirname($dest))) {
		mkdir(dirname($dest), 0755, true);
	}
	if (!rename($dir, $dest)) {
		throw new RuntimeException('No se pudo mover la entrada a la papelera.');
	}
	return cms_relative_path($dest);
}

function cms_delete_entry(string $relativeFile): array {
	$trash = cms_move_to_trash($relativeFile, 'deleted');
	$totalPages = cms_rebuild_archive_and_sitemap();
	return ['trash' => $trash, 'totalPages' => $totalPages];
}

function cms_combine_entries(string $targetFile, array $sourceFiles): array {
	$targetAbs = cms_resolve_entry_file($targetFile);
	$target = cms_parse_entry_file($targetAbs, $targetFile);
	if ($target === null) {
		throw new RuntimeException('No se pudo leer la entrada destino.');
	}
	$sources = array_values(array_unique(array_filter(array_map(fn($file): string => trim(str_replace('\\', '/', (string)$file), '/'), $sourceFiles))));
	$sources = array_values(array_filter($sources, fn(string $file): bool => $file !== '' && $file !== $targetFile));
	if (!$sources) {
		throw new RuntimeException('Selecciona al menos una entrada origen distinta al destino.');
	}
	$text = cms_normalize_space((string)$target['text']);
	$media = $target['mediaItems'];
	$moved = [];
	foreach ($sources as $sourceFile) {
		$sourceAbs = cms_resolve_entry_file($sourceFile);
		$source = cms_parse_entry_file($sourceAbs, $sourceFile);
		if ($source === null) {
			continue;
		}
		$media = array_merge($media, $source['mediaItems']);
		$sourceText = cms_normalize_space((string)$source['text']);
		if ($sourceText !== '') {
			$addition = $sourceText;
			if ($source['title'] !== $target['title'] && !str_contains($sourceText, $source['title'])) {
				$addition = $source['title']."\n\n".$sourceText;
			}
			$text = trim($text."\n\n".$addition);
		}
		$moved[] = cms_move_to_trash($sourceFile, 'combined');
	}
	$payload = [
		'title' => $target['title'],
		'summary' => $target['summary'],
		'date' => cms_date_input_value((string)$target['dateIso']),
		'text' => $text,
		'media' => cms_media_lines($media),
	];
	$result = cms_save_entry($payload, $targetFile);
	return ['target' => $result, 'moved' => $moved];
}

function cms_html_and_sitemap_files(): array {
	$files = [];
	$archive = CMS_ROOT.'/archivo';
	if (is_dir($archive)) {
		$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($archive, FilesystemIterator::SKIP_DOTS));
		foreach ($iterator as $file) {
			if ($file->isFile() && $file->getFilename() === 'index.html') {
				$files[] = $file->getPathname();
			}
		}
	}
	if (is_file(CMS_ROOT.'/sitemap.xml')) {
		$files[] = CMS_ROOT.'/sitemap.xml';
	}
	return $files;
}

function cms_update_references(string $oldEntryPath, string $newEntryPath, string $oldTitle, string $newTitle): int {
	if ($oldEntryPath === $newEntryPath && $oldTitle === $newTitle) {
		return 0;
	}
	$changed = 0;
	$oldRoot = '/'.$oldEntryPath.'/';
	$newRoot = '/'.$newEntryPath.'/';
	$oldAbsolute = CMS_DOMAIN.'/'.$oldEntryPath.'/';
	$newAbsolute = CMS_DOMAIN.'/'.$newEntryPath.'/';
	foreach (cms_html_and_sitemap_files() as $file) {
		$html = (string)file_get_contents($file);
		$before = $html;
		$html = str_replace([$oldRoot, $oldAbsolute], [$newRoot, $newAbsolute], $html);
		if ($oldTitle !== $newTitle && (str_contains($html, $newRoot) || str_contains($html, $newAbsolute))) {
			$html = str_replace(
				['<strong>'.cms_h($oldTitle).'</strong>', 'Miniatura de '.cms_h($oldTitle), 'Anterior: '.cms_h($oldTitle), 'Siguiente: '.cms_h($oldTitle)],
				['<strong>'.cms_h($newTitle).'</strong>', 'Miniatura de '.cms_h($newTitle), 'Anterior: '.cms_h($newTitle), 'Siguiente: '.cms_h($newTitle)],
				$html
			);
		}
		if ($html !== $before) {
			file_put_contents($file, $html);
			$changed++;
		}
	}
	return $changed;
}

function cms_replace_meta_content(string $html, string $key, string $value, string $content): string {
	$pattern = '/(<meta\b(?=[^>]*\b'.preg_quote($key, '/').'="'.preg_quote($value, '/').'")[^>]*\bcontent=")[^"]*("[^>]*>)/i';
	return preg_replace_callback($pattern, fn(array $match): string => $match[1].$content.$match[2], $html, 1) ?? $html;
}

function cms_set_preview_meta(string $html, string $previewImage): string {
	$previewImage = cms_preview_image_src($previewImage);
	$linePattern = '/^[\t ]*<meta\b(?=[^>]*\bname="cms:preview_image")[^>]*>\R?/mi';
	if ($previewImage === '') {
		return preg_replace($linePattern, '', $html, 1) ?? $html;
	}
	$tag = "\t<meta name=\"cms:preview_image\" content=\"".cms_h($previewImage)."\">\n";
	if (preg_match($linePattern, $html)) {
		return preg_replace($linePattern, $tag, $html, 1) ?? $html;
	}
	$count = 0;
	$updated = preg_replace('/^(\t<meta name="author" content="Daniel Estrella">)/m', $tag.'$1', $html, 1, $count);
	if ($count > 0 && $updated !== null) {
		return $updated;
	}
	$updated = preg_replace('/^(\t<meta name="description" content="[^"]*">\R?)/m', '$1'.$tag, $html, 1, $count);
	if ($count > 0 && $updated !== null) {
		return $updated;
	}
	return $html;
}

function cms_update_entry_title(string $relativeFile, string $newTitle): array {
	$newTitle = cms_normalize_space($newTitle);
	if ($newTitle === '') {
		throw new RuntimeException('El título no puede quedar vacío.');
	}
	$oldAbs = cms_resolve_entry_file($relativeFile);
	$html = (string)file_get_contents($oldAbs);
	$oldTitle = cms_extract_title($html);
	$oldDir = dirname($oldAbs);
	$parentDir = dirname($oldDir);
	$oldSlug = basename($oldDir);
	$parts = explode('/', trim($relativeFile, '/'));
	if (count($parts) !== 5) {
		throw new RuntimeException('La ruta de la entrada no tiene el formato esperado.');
	}
	$newSlug = cms_unique_slug($parentDir, cms_slugify($newTitle), $oldSlug);
	$newDir = $parentDir.'/'.$newSlug;
	$oldEntryPath = implode('/', array_slice($parts, 0, 4));
	$newEntryPath = implode('/', [$parts[0], $parts[1], $parts[2], $newSlug]);
	$titlePage = cms_h($newTitle).' — Daniel Estrella';
	$titleAttr = cms_h($newTitle.' — Daniel Estrella');
	$url = CMS_DOMAIN.'/'.$newEntryPath.'/';
	$urlAttr = cms_h($url);

	$html = preg_replace('/(<title>)[\s\S]*?(<\/title>)/', '$1'.$titlePage.'$2', $html, 1) ?? $html;
	$html = cms_replace_meta_content($html, 'property', 'og:title', $titleAttr);
	$html = cms_replace_meta_content($html, 'name', 'twitter:title', $titleAttr);
	$html = preg_replace_callback('/(<link\b[^>]*rel="canonical"[^>]*href=")[^"]*("[^>]*>)/i', fn(array $match): string => $match[1].$urlAttr.$match[2], $html, 1) ?? $html;
	$html = cms_replace_meta_content($html, 'property', 'og:url', $urlAttr);
	$html = preg_replace('/(<h1\b[^>]*>)[\s\S]*?(<\/h1>)/', '$1'.cms_linkify_text($newTitle).'$2', $html, 1) ?? $html;
	$html = preg_replace_callback(
		'/(<article\b[^>]*class="[^"]*)entrada-[a-z0-9-]+([^"]*")/i',
		fn(array $match): string => $match[1].'entrada-'.cms_h($newSlug).$match[2],
		$html,
		1
	) ?? $html;
	$html = cms_ensure_lazy_images($html);

	if ($newDir !== $oldDir) {
		if (is_dir($newDir)) {
			throw new RuntimeException('La ruta destino ya existe: '.cms_relative_path($newDir));
		}
		if (!rename($oldDir, $newDir)) {
			throw new RuntimeException('No se pudo renombrar la carpeta de la entrada.');
		}
		$oldAbs = $newDir.'/index.html';
	}
	if (file_put_contents($oldAbs, $html) === false) {
		throw new RuntimeException('No se pudo escribir la entrada actualizada.');
	}
	cms_update_references($oldEntryPath, $newEntryPath, $oldTitle, $newTitle);
	$totalPages = cms_rebuild_archive_and_sitemap();
	return [
		'file' => $newEntryPath.'/index.html',
		'path' => $newEntryPath,
		'url' => '/'.$newEntryPath.'/',
		'totalPages' => $totalPages,
	];
}

function cms_archive_link(int $page): string {
	return $page === 1 ? '/archivo/' : '/archivo/p/'.$page.'/';
}

function cms_archive_pagination_items(int $page, int $totalPages): string {
	$items = [];
	$previousVisible = 0;
	for ($num = 1; $num <= $totalPages; $num++) {
		$visible = $num === 1 || $num === $totalPages || abs($num - $page) <= 2;
		if (!$visible) {
			continue;
		}
		if ($previousVisible && $num > $previousVisible + 1) {
			$items[] = "\t\t\t\t\t<li><span class=\"paginacion-archivo__ellipsis\" aria-hidden=\"true\">...</span></li>";
		}
		if ($num === $page) {
			$items[] = "\t\t\t\t\t<li><span class=\"actual\" aria-current=\"page\"><span class=\"oculto-visualmente\">Página </span>".$num.'</span></li>';
		} else {
			$items[] = "\t\t\t\t\t<li><a href=\"".cms_archive_link($num)."\"><span class=\"oculto-visualmente\">Página </span>".$num.'</a></li>';
		}
		$previousVisible = $num;
	}
	return implode("\n", $items);
}

function cms_render_archive_preview(array $entry): string {
	$image = cms_preview_image_src((string)($entry['previewImage'] ?? ''));
	$media = is_array($entry['media'] ?? null) ? $entry['media'] : [];
	if ($image === '' && (($media['type'] ?? '') === 'video')) {
		$image = cms_preview_image_src((string)($media['poster'] ?? ''));
	}
	if ($image === '' && (($media['type'] ?? '') !== 'video')) {
		$image = cms_preview_image_src((string)($media['src'] ?? ''));
	}
	$image = $image !== '' ? $image : CMS_DEFAULT_PREVIEW_IMAGE;
	$isVideo = ($entry['media']['type'] ?? '') === 'video' || str_contains((string)($entry['html'] ?? ''), '<video');
	return "\t\t\t\t\t<article class=\"preview-entrada".($isVideo ? ' preview-entrada--video' : '')."\">\n".
		"\t\t\t\t\t\t<a href=\"".cms_h($entry['url'])."\">\n".
		"\t\t\t\t\t\t\t<img src=\"".cms_h($image)."\" alt=\"Miniatura de ".cms_h($entry['title'])."\" loading=\"lazy\">\n".
		"\t\t\t\t\t\t\t<strong>".cms_h($entry['title'])."</strong>\n".
		"\t\t\t\t\t\t\t<time datetime=\"".cms_h($entry['dateIso'])."\">".cms_h(cms_local_info((string)$entry['dateIso'])['archiveDate'])."</time>\n".
		"\t\t\t\t\t\t</a>\n".
		"\t\t\t\t\t</article>";
}

function cms_render_archive_page(array $entries, int $page, int $totalPages): string {
	$title = $page === 1 ? 'Archivo de Daniel Estrella' : 'Archivo de Daniel Estrella - Página '.$page;
	$h1 = $page === 1 ? 'Archivo de Daniel Estrella' : 'Archivo de Daniel Estrella, página '.$page;
	$description = 'Archivo de entradas de Daniel Estrella sobre testing, tecnologia, cultura digital, proyectos personales y vida cotidiana.'.($page > 1 ? ' Pagina '.$page.' de '.$totalPages.'.' : '');
	$canonical = CMS_DOMAIN.cms_archive_link($page);
	$prev = $page > 1 ? "\t<link rel=\"prev\" href=\"".CMS_DOMAIN.cms_archive_link($page - 1)."\">\n" : '';
	$next = $page < $totalPages ? "\t<link rel=\"next\" href=\"".CMS_DOMAIN.cms_archive_link($page + 1)."\">\n" : '';
	$sections = [];
	foreach ($entries as $entry) {
		$year = substr((string)$entry['dateIso'], 0, 4);
		$index = count($sections);
		if (!$sections || $sections[$index - 1]['year'] !== $year) {
			$first = $page === 1 && $index === 0;
			$sections[] = [
				'year' => $year,
				'heading' => $first ? 'Entradas recientes' : 'Entradas de '.$year,
				'id' => $first ? 'titulo-archivo-entradas' : 'titulo-archivo-'.$year.'-'.$index,
				'entries' => [],
			];
		}
		$sections[count($sections) - 1]['entries'][] = $entry;
	}
	$sectionsHtml = [];
	foreach ($sections as $section) {
		$previews = implode("\n", array_map('cms_render_archive_preview', $section['entries']));
		$sectionsHtml[] = "\t\t\t<section class=\"archivo-entradas\" aria-labelledby=\"".cms_h($section['id'])."\">\n".
			"\t\t\t\t<h2 id=\"".cms_h($section['id'])."\">".cms_h($section['heading'])."</h2>\n".
			"\t\t\t\t<div class=\"grid-masonry-entradas\">\n".$previews."\n\t\t\t\t</div>\n\t\t\t</section>";
	}
	$pagination = "\t\t\t<nav class=\"paginacion-archivo\" aria-label=\"Paginación del archivo\">\n".
		"\t\t\t\t<p>Página ".$page.' de '.$totalPages."</p>\n".
		"\t\t\t\t<div class=\"paginacion-archivo__saltos\">\n".
		"\t\t\t\t\t".($page > 1 ? '<a class="paginacion-archivo__salto" href="'.cms_archive_link($page - 1).'">Anterior</a>' : '<span class="paginacion-archivo__salto deshabilitado">Anterior</span>')."\n".
		"\t\t\t\t\t".($page < $totalPages ? '<a class="paginacion-archivo__salto" href="'.cms_archive_link($page + 1).'">Siguiente</a>' : '<span class="paginacion-archivo__salto deshabilitado">Siguiente</span>')."\n".
		"\t\t\t\t</div>\n\t\t\t\t<ol>\n".cms_archive_pagination_items($page, $totalPages)."\n\t\t\t\t</ol>\n\t\t\t</nav>";
	$logo = cms_logo_svg();
	return '<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>'.cms_h($title).'</title>
	<meta name="description" content="'.cms_h($description).'">
	<meta property="og:title" content="'.cms_h($title).'">
	<meta property="og:description" content="'.cms_h($description).'">
	<meta property="og:type" content="website">
	<meta property="og:url" content="'.cms_h($canonical).'">
	<meta property="og:image" content="'.CMS_DOMAIN.'/portada.webp">
	<link rel="canonical" href="'.cms_h($canonical).'">
'.$prev.$next.'	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lavishly+Yours&amp;family=Victor+Mono:ital,wght@0,100..700;1,100..700&amp;display=swap" rel="stylesheet">
	<link rel="stylesheet" href="https://danielestrella.com/css/fuentes.min.css">
	<link rel="stylesheet" href="'.cms_h(cms_current_css_href()).'">
</head>
<body>
	<header>
		<p><a href="/">'.$logo.' <strong>Daniel Estrella</strong></a></p>
		<p>Sitio personal y reflexiones ✨</p>
		<p class="estadisticas"><span class="actual">🗄️ Archivo</span></p>
	</header>
	<main>
		<article class="indice archivo">
			<h1>'.cms_h($h1).'</h1>
'.implode("\n", $sectionsHtml).'
'.$pagination.'
		</article>
	</main>
	<footer class="pie-de-pagina">
		<p>✨ DanielEstrella.com — DEstrella.mx ✨</p>
		<p class="enlaces-al-pie"><a href="/">🦊 Inicio</a></p>
		<p>🌸 Hecho con 💖 y mucho ☕️</p>
	</footer>
	<script src="'.cms_h(cms_current_js_src()).'"></script>
</body>
</html>
';
}

function cms_xml_escape(string $value): string {
	return str_replace(['&', '<', '>', '"'], ['&amp;', '&lt;', '&gt;', '&quot;'], $value);
}

function cms_rebuild_sitemap(array $entries, int $totalPages): void {
	$today = (new DateTimeImmutable('now', new DateTimeZone(CMS_TIMEZONE)))->format('Y-m-d\T00:00:00P');
	$urls = [
		['loc' => CMS_DOMAIN.'/', 'lastmod' => '2021-03-29T18:52:28+00:00', 'priority' => '1.00'],
	];
	for ($page = 1; $page <= $totalPages; $page++) {
		$urls[] = [
			'loc' => CMS_DOMAIN.cms_archive_link($page),
			'lastmod' => $today,
			'priority' => $page === 1 ? '0.80' : '0.70',
		];
	}
	foreach ($entries as $entry) {
		$urls[] = [
			'loc' => CMS_DOMAIN.$entry['url'],
			'lastmod' => (string)$entry['dateIso'],
			'priority' => '0.80',
		];
	}
	$body = array_map(fn(array $url): string => '<url>
  <loc>'.cms_xml_escape($url['loc']).'</loc>
  <lastmod>'.cms_xml_escape($url['lastmod']).'</lastmod>
  <priority>'.$url['priority'].'</priority>
</url>', $urls);
	file_put_contents(CMS_ROOT.'/sitemap.xml', '<?xml version="1.0" encoding="UTF-8"?>
<urlset
      xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
'.implode("\n", $body).'
</urlset>
');
}

function cms_rebuild_archive_and_sitemap(): int {
	$entries = cms_scan_entries();
	$totalPages = max(1, (int)ceil(count($entries) / CMS_PER_PAGE));
	for ($page = 1; $page <= $totalPages; $page++) {
		$pageEntries = array_slice($entries, ($page - 1) * CMS_PER_PAGE, CMS_PER_PAGE);
		$dir = $page === 1 ? CMS_ROOT.'/archivo' : CMS_ROOT.'/archivo/p/'.$page;
		if (!is_dir($dir)) {
			mkdir($dir, 0755, true);
		}
		file_put_contents($dir.'/index.html', cms_render_archive_page($pageEntries, $page, $totalPages));
	}
	$pRoot = CMS_ROOT.'/archivo/p';
	if (is_dir($pRoot)) {
		foreach (glob($pRoot.'/*', GLOB_ONLYDIR) ?: [] as $dir) {
			$page = (int)basename($dir);
			if ($page > $totalPages && is_file($dir.'/index.html')) {
				unlink($dir.'/index.html');
				@rmdir($dir);
			}
		}
	}
	cms_rebuild_sitemap($entries, $totalPages);
	return $totalPages;
}

function cms_flash(string $type, string $text, string $focus = ''): void {
	$_SESSION['cms_flash'][] = ['type' => $type, 'text' => $text];
	if ($focus !== '') {
		$_SESSION['cms_focus'] = $focus;
	}
}

function cms_take_flash(): array {
	$flash = $_SESSION['cms_flash'] ?? [];
	unset($_SESSION['cms_flash']);
	return $flash;
}

function cms_redirect(string $url = 'index.php'): never {
	header('Location: '.$url);
	exit;
}

function cms_entry_form_defaults(?array $entry = null): array {
	if ($entry === null) {
		return [
			'file' => '',
			'title' => '',
			'summary' => '',
			'date' => (new DateTimeImmutable('now', new DateTimeZone(CMS_TIMEZONE)))->format('Y-m-d\TH:i:s'),
			'text' => '',
			'media' => '',
			'metaImage' => '',
			'previewImage' => '',
			'imageOptions' => [],
			'blocks' => [cms_default_block()],
			'isComplex' => false,
			'contentHtml' => '',
		];
	}
	$blocks = cms_extract_section_blocks((string)$entry['html']);
	$imageOptions = cms_block_image_urls($blocks);
	if (!$imageOptions) {
		$imageOptions = array_values(array_filter([cms_first_image_src($entry['mediaItems'])]));
	}
	return [
		'file' => $entry['file'],
		'title' => $entry['title'],
		'summary' => $entry['summary'],
		'date' => cms_date_input_value((string)$entry['dateIso']),
		'text' => $entry['text'],
		'media' => cms_media_lines($entry['mediaItems']),
		'metaImage' => $entry['metaImage'],
		'previewImage' => $entry['previewImage'],
		'imageOptions' => $imageOptions,
		'blocks' => $blocks,
		'isComplex' => cms_is_complex_entry_html((string)$entry['html']),
		'contentHtml' => cms_extract_content_html((string)$entry['html']),
	];
}
