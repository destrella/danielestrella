<?php
$args = FALSE;
if(PHP_SAPI == 'cli'):
	$args = getopt(
		't::',
		['debug','forzar','dominio::','archivo::','rutatipo::']
	);
elseif(!empty($_SERVER['REQUEST_METHOD'])):
	if($_SERVER['REQUEST_METHOD']=='POST'):
		$args = $_POST;
		define('MSGHTM', TRUE);
	elseif($_SERVER['REQUEST_METHOD']=='GET'):
		$args = $_GET;
		define('MSGHTM', TRUE);
	endif;
endif;
if(!$args):
	$args = [];
endif;
if(!defined('MSGHTM')):
	define('MSGHTM', FALSE);
endif;

//Valida el dominio recibido, si existe
if(!empty($args['dominio'])
&& filter_var($args['dominio'], FILTER_VALIDATE_URL, ['flags'=>'FILTER_FLAG_SCHEME_REQUIRED | FILTER_FLAG_HOST_REQUIRED'])):
	$args['dominio'] = parse_url($args['dominio']);
	if($args['dominio']['scheme']=='https'
	|| $args['dominio']['scheme']=='http'):
		if(!empty($args['dominio']['path'])):
			$args['dominio']['path'] = rtrim($args['dominio']['path'],'/');
		endif;
		$args['dominio'] = $args['dominio']['scheme'].'://'.$args['dominio']['host'].$args['dominio']['path'];
	else:
		$args['dominio'] = '';
	endif;
endif;
//Define el dominio del sitio
if(!empty($args['dominio'])):
	define('DOM', $args['dominio']);
else:
	define('DOM', 'https://danielestrella.com');
endif;
//Define la ruta a guardar los archivos generados
if(!empty($args['archivo'])):
	define('ARC', $args['archivo']);
else:
	define('ARC', 'archivo/');
endif;
//Define el tipo de ruta: relativa o absoluta
if(!empty($args['rutatipo'])
&& ($args['rutatipo'] == 'relativa' || $args['rutatipo'] == 'absoluta')):
	define('RUTA', $args['rutatipo']);
else:
	define('RUTA', 'absoluta');
endif;
//Define si imprime mensajes de depuración o no
if(isset($args['debug'])):
	define('DEBUG', TRUE);
else:
	define('DEBUG', FALSE);
endif;
//Define si se vuelven a generar las entradas existentes
if(isset($args['forzar'])):
	define('FORZAR', TRUE);
else:
	define('FORZAR', FALSE);
endif;

const DÍA = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
const MES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const TIPO = ['artículo', 'foto', 'diapositivas', 'galería', 'estado', 'enlace', 'cita', 'video'];
const NOFOTO = '/img/imagen-rota.png';
const PORTADA = '/portada.webp';
const CSS_VERSION = '202605292350';
const JS_VERSION = '202605292315';
include('inc/funciones.php');
const LOGO = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3000 1970" aria-hidden="true"><path d="M468 1938a707 707 0 01-303-187A545 545 0 012 1451c-6-65 11-146 33-160 6-3 66 49 134 117 182 184 305 239 532 240 100 0 143-6 212-27a732 732 0 00472-518c38-129 40-242 8-368a498 498 0 00-132-250c-149-163-383-191-660-78-136 56-196 66-299 47C155 426 46 351 13 251-9 191-1 0 21 0c5 0 18 19 31 43s42 52 68 65c70 36 144 30 304-26 125-44 152-50 253-50 126-1 217 19 346 79a972 972 0 01429 376c22 39 44 72 47 72 4 0 25-33 48-72a972 972 0 01429-376c128-60 220-80 345-79 101 0 129 6 254 50 159 56 233 62 304 26 26-13 54-41 67-65 14-24 27-43 31-43 11 0 23 79 23 156 0 180-154 300-387 301-86 2-107-3-216-48-274-115-511-87-660 76a498 498 0 00-132 250c-22 89-28 240-12 300 6 24 10 20 23-29 20-75 117-172 198-200 90-31 153-26 244 18 65 32 93 38 162 40 95 0 140-22 190-90l30-40v56c0 120-34 224-100 291-99 106-218 117-411 37-86-35-94-37-162-25-55 11-78 22-105 50l-32 36 35 69a736 736 0 00456 385c79 23 288 19 373-6 132-39 207-88 336-219 68-68 127-120 133-117 23 14 39 95 33 160-9 94-70 205-163 300a730 730 0 01-320 192c-76 22-105 25-220 19-165-9-290-40-434-111a746 746 0 01-312-288c-23-41-44-73-48-73-3 0-25 32-47 73-135 238-416 386-757 397-127 5-152 3-227-22z"/></svg>';

$entradas = [];
/**Incluye todos los archivos .php en la carpeta "src", si existe. */
$fuentes = glob('src/*.php') ?: [];
if(empty($fuentes)):
	echo _r('ⓘ No se encontraron fuentes en src/*.php; no hay entradas que generar.', __LINE__);
endif;
foreach($fuentes as $archivo):
	include $archivo;
endforeach;

//foreach($entradas as $k=>$v){echo '['.date('m/Y',$k).'] ';}echo PHP_EOL;
krsort($entradas);
//foreach($entradas as $k=>$v){echo '['.date('m/Y',$k).'] ';}echo PHP_EOL;
//die();
//Define que tipo de entradas generar
if(isset($args['t'])):
	//Muestra noticia de que se generará sólo un tipo de entradas
	echo _r('ⓘ Se ha pedido generar sólo entradas del tipo "'.$args['t'].'".', __LINE__);
	//Comprueba que se pida un tipo válido
	if(!is_numeric($args['t'])
	|| !isset(TIPO[$args['t']])):
		//Si no lo es, muestra alerta
		echo _r('❗ ¡El tipo "'.$args['t'].'" no existe!', __LINE__);
		$msg = 'ⓘ Los tipos aceptados son: ';
		foreach(TIPO as $k=>$v):
			$msg .= $k.' ('.$v.'), ';
		endforeach;
		$msg = rtrim($msg, ', ').'.';
		echo _r($msg, __LINE__);
		//y aborta
		return FALSE;
	else:
		//Genera sólo este tipo de entradas
		$generatipo = $args['t'];
	endif;
else:
	//Genera todas los tipos de entradas
	$generatipo = FALSE;
endif;

//Funciones para crear la ruta (slug) a partir del título
include('inc/wp-sanitize.php');

if(RUTA == 'relativa'):
	// archivo/año/mes/nota/index.html
	define('URI', '../../../..');
	define('TRAILING', '/index.html');
	define('URIPAG', '../..');
else:
	define('URI', DOM);
	define('TRAILING', '/');
	define('URIPAG', DOM);
endif;
function rutaArchivoIndice($pagina, $paginaActual = 1){
	if(RUTA == 'relativa'):
		if($pagina == 1):
			return $paginaActual == 1 ? './index.html' : '../../index.html';
		endif;
		return $paginaActual == 1 ? 'p/'.$pagina.'/index.html' : '../'.$pagina.'/index.html';
	endif;
	return $pagina == 1 ? DOM.'/archivo/' : DOM.'/archivo/p/'.$pagina.'/';
}

//Variables para crear las páginas del archivo.
$paginasindice = 1;
$totalporindice = 18;
$totalactual = 0;
$masentradas = [];

$indice = array_keys($entradas);
foreach($entradas as $t=>$entrada):
	$entrada['t'] = $t;
	$entrada = normalizaEntrada($entrada, $t);

	if(isset($indice[array_search($t, $indice)+1])):
		$antid = $indice[array_search($t, $indice)+1];

		$entant = normalizaEntrada($entradas[$antid], $antid);
	else:
		$entant = FALSE;
	endif;
	if(isset($indice[array_search($t, $indice)-1])):
		$sigid = $indice[array_search($t, $indice)-1];
		$entsig = normalizaEntrada($entradas[$sigid], $sigid);
	else:
		$entsig = FALSE;
	endif;

	//Si no existe el tipo especificado, usa 'artículo' por defecto
	if(!isset(TIPO[$entrada['tipo']])):
		echo _r('⚠️ Tipo '.$entrada['tipo'].' desconocido, usando 0 (artículo) por defecto.', __LINE__);
		$entrada['tipo'] = 0;
	endif;

	//Si se a definido crear sólo cierto tipo
	if($generatipo !== FALSE
	&& $generatipo != $entrada['tipo']):
		//Si es la última entrada
		if($entrada['t'] == array_key_last($entradas)):
			//Y no encontró ninguna del tipo, imprime noticia
			echo _r('❗ No se encontró ninguna entrada del tipo '.$generatipo.' ('.TIPO[$generatipo].')', __LINE__);
			//Termina la ejecución
			return FALSE;
		endif;
		//El tipo no coincide con la entrada actual, salta a la siguiente
		continue;
	endif;

	//Estructura de archivo en base a año y mes
	$publicado = $entrada['slug'].'/index.html';
	$entrada['contenido'] = trim($entrada['contenido']);

	$cssadicional = '';

	//Si no existe resúmen crea uno a partir del contenido
	$entrada['resumen'] = trim($entrada['resumen']);
	if(empty($entrada['resumen'])):
		$entrada['resumen'] = generaResumen($entrada['contenido']);
	endif;

	$cuerpo = '';
	//Comprueba el tipo de entrada a usar
	$función = 'e'.TIPO[$entrada['tipo']];
	if(is_callable($función)):
		$cnt = $función($entrada);
		$cuerpo .= $cnt['cuerpo'];
	else:
		$cnt = eartículo($entrada);
		$cuerpo .= $cnt['cuerpo'];
	endif;

	if(RUTA == 'relativa'):
		if($paginasindice == 1):
			$uripag = '..';
		else:
			$uripag = '../../..';
		endif;
	else:
		$uripag = URIPAG;
	endif;

	if(!empty($masentradas[$paginasindice])):
		$masentradas[$paginasindice] .= resumenparaindice($entrada, $uripag);
	else:
		$masentradas[$paginasindice] = resumenparaindice($entrada, $uripag);
	endif;
	$totalactual++;
	if($totalactual >= $totalporindice):
		$paginasindice++;
		$totalactual = 0;
	endif;

	$fotodestacada = DOM.PORTADA;
	$preconnect = '';
	if(!empty($entrada['fotos'][0]['src'])):
		$fotodestacada = cloudtohotlink($entrada['fotos'][0]['src']);
		if(strpos($fotodestacada, '/') === 0):
			$fotodestacada = DOM.$fotodestacada;
		endif;
		$foturl = parse_url($fotodestacada);
		if(!empty($foturl['scheme']) && !empty($foturl['host'])):
			$foturl = $foturl['scheme'].'://'.$foturl['host'];
			$preconnect =
			'<link rel="preconnect" href="'.$foturl.'" crossorigin>'.
			'<link rel="dns-prefetch" href="'.$foturl.'">';
		endif;
	endif;

	if($entant || $entsig):
		$navantsig = '<nav class="navegacion-entrada" aria-label="Entradas cercanas">';
		if($entsig):
			$navantsig .=
			'<a href="'.URI.'/'.$entsig['slug'].TRAILING.'">Anterior: '.h($entsig['título']).'</a>';
		endif;
		if($entant):
			$navantsig .=
			'<a href="'.URI.'/'.$entant['slug'].TRAILING.'">Siguiente: '.h($entant['título']).'</a>';
		endif;
		$navantsig .= '</nav>';
	else:
		$navantsig = '';
	endif;
	$cssadicional .=
	$cnt['CSSadicional'].
	$entrada['inlinecs'];

	if(!is_file($publicado)
	|| DEBUG || FORZAR):
		//Crea ruta de directorios si no existe
		if(!is_dir($entrada['slug'])):
			echo _r('ⓘ Directorio(s) '.$entrada['slug'].' no existe(n),', __LINE__);
			if(mkdir($entrada['slug'], 0766, true)):
				echo _r('✓ Directorio '.$entrada['slug'].' creado con éxito.', __LINE__);
			else:
				echo _r('❌ Error al crear el directorio '.$entrada['slug'].' saltando a siguiente entrada.', __LINE__);
				continue;
			endif;
		endif;

		$titulohtml = h($entrada['título']);
		$resumenhtml = h($entrada['resumen']);
		$canonical = DOM.'/'.$entrada['slug'].TRAILING;
		$anioentrada = date('Y', $entrada['t']);
		//Plantilla
		$html =<<<HTML
<!DOCTYPE html>
<html lang="{$entrada['idioma']}">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{$titulohtml} — Daniel Estrella</title>
	<meta name="description" content="{$resumenhtml}">
	<meta name="author" content="Daniel Estrella">
	<meta name="robots" content="index,follow,max-image-preview:large">
	<link rel="canonical" href="{$canonical}">
	<meta property="og:locale" content="es_MX">
	<meta property="og:site_name" content="Daniel Estrella">
	<meta property="og:type" content="article">
	<meta property="og:title" content="{$titulohtml} — Daniel Estrella">
	<meta property="og:description" content="{$resumenhtml}">
	<meta property="og:url" content="{$canonical}">
	<meta property="og:image" content="{$fotodestacada}">
	<meta property="article:published_time" content="{$entrada['fechaISO']}">
	<meta property="article:author" content="Daniel Estrella">
	<meta property="article:section" content="Archivo {$anioentrada}">
	<meta name="twitter:card" content="summary_large_image">
	<meta name="twitter:title" content="{$titulohtml} — Daniel Estrella">
	<meta name="twitter:description" content="{$resumenhtml}">
	<meta name="twitter:image" content="{$fotodestacada}">
	{$preconnect}
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lavishly+Yours&amp;family=Victor+Mono:ital,wght@0,100..700;1,100..700&amp;display=swap" rel="stylesheet">
	<link rel="stylesheet" href="{$_(DOM)}/css/fuentes.min.css">
	<link rel="stylesheet" href="{$_(URI)}/css/estilos.css?v={$_(CSS_VERSION)}">
	{$cssadicional}
	<meta name="generator" content="DEstrella.mx">
</head>
<body>
	<header>
		<p><a href="{$_(URI)}{$_(TRAILING)}">{$_(LOGO)} <strong>Daniel Estrella</strong></a></p>
		<p>Sitio personal y reflexiones ✨</p>
		<p class="estadisticas"><a href="{$_(URI)}/archivo{$_(TRAILING)}">🗄️ Archivo</a></p>
	</header>
	<main>
		{$cuerpo}
		{$navantsig}
	</main>
	<footer class="pie-de-pagina">
		<p>✨ DanielEstrella.com — DEstrella.mx ✨</p>
		<p class="enlaces-al-pie"><a href="{$_(URI)}{$_(TRAILING)}">🦊 Inicio</a> <a href="{$_(URI)}/archivo{$_(TRAILING)}">🗄️ Archivo</a></p>
		<p>🌸 Hecho con 💖 y mucho ☕️</p>
	</footer>
	<script src="{$_(URI)}/js/scripts.js?v={$_(JS_VERSION)}"></script>
</body>
</html>
HTML;
		$html = minifyhtml($html);
		if(file_put_contents($publicado, $html)!==FALSE):
			echo _r('✓ Entrada "'.$entrada['título'].'" creada con éxito. ('.$publicado.')', __LINE__);
		else:
			echo _r('❌ Error al crear la entrada ['.$publicado.'].', __LINE__);
		endif;
	else:
		echo _r('ⓘ "'.$entrada['título'].'" ya existe. ('.$publicado.')', __LINE__);
	endif;
	if(DEBUG):
		//Se detiene después del primer contenido
		//break 1;
	endif;
endforeach;

//Índices
$totalpáginas = count($masentradas);
foreach($masentradas as $k => $v):
	$uri = RUTA == 'relativa' ? '..' : URI;
	if($k == 1):
		$idxtítulo = 'Archivo de Daniel Estrella';
		$h1 = 'Archivo de Daniel Estrella';
		$slug = DOM.'/archivo/';
		$rutaidx = 'archivo';
	else:
		$idxtítulo = 'Archivo de Daniel Estrella - Página '.$k;
		$h1 = 'Archivo de Daniel Estrella, página '.$k;
		$slug = DOM.'/archivo/p/'.$k.'/';
		if(RUTA == 'relativa'):
			$uri = '../../..';
		endif;
		$rutaidx = 'archivo/p/'.$k;
	endif;
	//Paginación
	if($totalpáginas > 1):
		$anterior = $k > 1
		? '<a class="paginacion-archivo__salto" href="'.rutaArchivoIndice($k - 1, $k).'">Anterior</a>'
		: '<span class="paginacion-archivo__salto deshabilitado">Anterior</span>';
		$siguiente = $k < $totalpáginas
		? '<a class="paginacion-archivo__salto" href="'.rutaArchivoIndice($k + 1, $k).'">Siguiente</a>'
		: '<span class="paginacion-archivo__salto deshabilitado">Siguiente</span>';
		$paginación =
		'<nav class="paginacion-archivo" aria-label="Paginación del archivo">'.
		'<p>Página '.$k.' de '.$totalpáginas.'</p>'.
		'<div class="paginacion-archivo__saltos">'.$anterior.$siguiente.'</div>'.
		'<ol>';
		for($i = 1; $i <= count($masentradas); $i++):
			if($i == $k):
				$paginación .=
				'<li><span class="actual" aria-current="page">'.
				'<span class="oculto-visualmente">Página </span>'.$i.
				'</span></li>';
			else:
				$paginación .=
				'<li><a href="'.rutaArchivoIndice($i, $k).'">'.
				'<span class="oculto-visualmente">Página </span>'.$i.
				'</a></li>';
			endif;
		endfor;
		$paginación .= '</ol></nav>';
	else:
		$paginación = '';
	endif;
	$prevnext = '<link rel="canonical" href="'.$slug.'">';
	if($k > 1):
		$prevnext .= '<link rel="prev" href="'.($k == 2 ? DOM.'/archivo/' : DOM.'/archivo/p/'.($k - 1).'/').'">';
	endif;
	if($k < $totalpáginas):
		$prevnext .= '<link rel="next" href="'.DOM.'/archivo/p/'.($k + 1).'/">';
	endif;
	$descripcion = 'Archivo de entradas de Daniel Estrella sobre testing, tecnología, cultura digital, proyectos personales y vida cotidiana.';
	if($k > 1):
		$descripcion .= ' Página '.$k.' de '.$totalpáginas.'.';
	endif;
	$descripcionhtml = h($descripcion);
	//Plantilla índices
	$html =<<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>{$idxtítulo}</title>
	<meta name="description" content="{$descripcionhtml}">
	<meta property="og:title" content="{$idxtítulo}">
	<meta property="og:description" content="{$descripcionhtml}">
	<meta property="og:type" content="website">
	<meta property="og:url" content="{$slug}">
	<meta property="og:image" content="{$_(DOM)}{$_(PORTADA)}">
	{$prevnext}
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Lavishly+Yours&amp;family=Victor+Mono:ital,wght@0,100..700;1,100..700&amp;display=swap" rel="stylesheet">
	<link rel="stylesheet" href="{$_(DOM)}/css/fuentes.min.css">
	<link rel="stylesheet" href="{$uri}/css/estilos.css?v={$_(CSS_VERSION)}">
	<meta name="generator" content="DEstrella.mx">
</head>
<body>
	<header>
		<p><a href="{$uri}{$_(TRAILING)}">{$_(LOGO)} <strong>Daniel Estrella</strong></a></p>
		<p>Sitio personal y reflexiones ✨</p>
		<p class="estadisticas"><span class="actual">🗄️ Archivo</span></p>
	</header>
	<main>
		<article class="indice archivo">
			<h1>{$h1}</h1>
			<section class="archivo-entradas" aria-labelledby="titulo-archivo-entradas">
				<h2 id="titulo-archivo-entradas">Entradas del archivo</h2>
				<div class="grid-masonry-entradas">
					{$v}
				</div>
			</section>
			{$paginación}
		</article>
	</main>
	<footer class="pie-de-pagina">
		<p>✨ DanielEstrella.com — DEstrella.mx ✨</p>
		<p class="enlaces-al-pie"><a href="{$uri}{$_(TRAILING)}">🦊 Inicio</a></p>
		<p>🌸 Hecho con 💖 y mucho ☕️</p>
	</footer>
	<script src="{$uri}/js/scripts.js?v={$_(JS_VERSION)}"></script>
</body>
</html>
HTML;
	$html = minifyhtml($html);
	//Crea ruta de directorios si no existe
	if(!is_dir($rutaidx)):
		echo _r('ⓘ Directorio(s) '.$rutaidx.' no existe(n),', __LINE__);
		if(mkdir($rutaidx, 0766, true)):
			echo _r('✓ Directorio '.$rutaidx.' creado con éxito.', __LINE__);
		else:
			echo _r('❌ Error al crear el directorio '.$rutaidx.' saltando a siguiente entrada.', __LINE__);
			continue;
		endif;
	endif;
	$rutaidx .= '/index.html';
	if(file_put_contents($rutaidx, $html)!==FALSE):
		echo _r('✓ Índice "'.$k.'" creado con éxito. ('.$rutaidx.')', __LINE__);
	else:
		echo _r('❌ Error al crear el índice ['.$rutaidx.'].', __LINE__);
	endif;

endforeach;
?>
