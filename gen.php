<?php
$args = FALSE;
if(PHP_SAPI == 'cli'):
	$args = getopt(
		't::',
		['debug','reindexar','dominio::','archivo::','rutatipo::']
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
&& ($args['rutatipo'] == 'relativa' || $args['rutatipo'] = 'absoluta')):
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
//Define si se reindexan las entradas
if(isset($args['reindexar'])):
	$reindexar = TRUE;
else:
	$reindexar = FALSE;
endif;

const DÍA = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
const MES = ['', 'Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
const TIPO = ['artículo', 'foto', 'diapositivas', 'galería', 'estado', 'enlace', 'cita', 'video'];
const IMGURL = 'https://beewax.com.mx/i/';
const NOFOTO = IMGURL.'nofoto.png';
include('inc/funciones.php');

$entradas = [];
/**Incluye todos los archivos .php en la carpeta "src" */
/*
foreach (glob("src/*.php") as $archivo):
	include $archivo;
endforeach;
*/
//include 'src/2003.php';
include 'src/2021.php';

//foreach($entradas as $k=>$v){echo '['.date('m/Y',$k).'] ';}echo PHP_EOL;
ksort($entradas);
//foreach($entradas as $k=>$v){echo '['.date('m/Y',$k).'] ';}echo PHP_EOL;
//die();
//Total de entradas
$total = count($entradas);

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
	$uri = '../../../..';// archivo/año/mes/nota/index.html
	$trailing = '/index.html';
else:
	$uri = DOM;
	$trailing = '/';
endif;

$indice = array_keys($entradas);
foreach($entradas as $t=>$entrada):
	$entrada['t'] = $t;
	$entrada = normalizaEntrada($entrada, $t);

	if(isset($indice[array_search($t, $indice)-1])):
		$antid = $indice[array_search($t, $indice)-1];

		$entant = normalizaEntrada($entradas[$antid], $antid);
	else:
		$entant = FALSE;
	endif;
	if(isset($indice[array_search($t, $indice)+1])):
		$sigid = $indice[array_search($t, $indice)+1];
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

	$timetag = '<time datetime="'.date('c', $t).'" title="'.$entrada['fechaLarga'].'">'.$entrada['fechaLarga'].'</time>';
	//Estructura de archivo en base a año y mes
	$publicado = $entrada['slug'].'/index.html';
	//Si no existe resúmen crea uno a partir del contenido
	$entrada['resumen'] = trim($entrada['resumen']);
	$resumen = true;
	if(empty($entrada['resumen'])):
		$resumen = false;
		$entrada['resumen'] = generaResumen($entrada['contenido']);
	endif;
	$entrada['contenido'] = trim($entrada['contenido']);

	$cssadicional = '';
	$jsadicional = ['encabezado'=>'', 'pie'=>''];

	if(!is_file($publicado)
	|| DEBUG):
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

		if(DEBUG):/**Usa versión sin minificar si es depuración */
			$min = '';
		else:/**Versión minificada si no lo es */
			$min = '.min';
		endif;

		$cuerpo = '';
		//Comprueba el tipo de entrada a usar
		if(is_file('inc/p'.TIPO[$entrada['tipo']].'.php')):
			include 'inc/p'.TIPO[$entrada['tipo']].'.php';
		else:
			$cuerpo .=
			'<article class="'.TIPO[$entrada['tipo']].'">'.
			'<h2>'.$entrada['título'].'</h2>';
			if($resumen && !empty($entrada['resumen'])):
				$cuerpo .= '<p>'.$entrada['resumen'].'</p>';
			endif;
			if(!empty($entrada['contenido'])):
				$cuerpo .= $entrada['contenido'];
			endif;
			$cuerpo .= '<footer>'.$timetag.'</footer></article>';
		endif;

		if(!empty($entrada['fotos'][0]['src'])):
			$fotodestacada = $entrada['fotos'][0]['src'];
			$foturl = parse_url($entrada['fotos'][0]['src']);
			$foturl = $foturl['scheme'].'://'.$foturl['host'];
			$preconnect =
			'<link rel="preconnect" href="'.$foturl.'" crossorigin>'.
			'<link rel="dns-prefetch" href="'.$foturl.'">';
		else:
			$fotodestacada = NOFOTO;
			$preconnect = '';
		endif;

		if($entant || $entsig):
			$navantsig = '<nav class="antes-despues">';
			if($entsig):
				$navantsig .=
				'<a class="izq" href="'.$uri.'/'.$entsig['slug'].$trailing.'" title="'.$entsig['título'].'">&#62212;</a>';
			endif;
			if($entant):
				$navantsig .=
				'<a class="der" href="'.$uri.'/'.$entant['slug'].$trailing.'" title="'.$entant['título'].'">&#62213;</a>';
			endif;
			$navantsig .= '</nav>';
		else:
			$navantsig = '';
		endif;
		//Plantilla
		$html =<<<HTML
<!doctype html>
<html class="no-js" lang="es-MX">
<head>
	<meta charset="utf-8">
	<title>
		{$entrada['título']} — DanielEstrella.com
	</title>
	{$preconnect}
	<meta name="description" content="{$entrada['resumen']}">
	<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
	<meta property="og:title" content="{$entrada['título']} — DanielEstrella.com">
	<meta property="og:type" content="website">
	<meta property="og:url" content="{$_(DOM)}/{$entrada['slug']}{$trailing}">
	<meta property="og:image" content="{$fotodestacada}">
	<link rel="apple-touch-icon" href="{$_(IMGURL)}apple-touch-icon.png">
	<script src="{$uri}/js/prefixfree.min.js"></script>
	<link rel="stylesheet" href="{$uri}/css/fuentes{$min}.css">
	<link rel="stylesheet" href="{$uri}/css/estilos{$min}.css">
	{$cssadicional}
	<meta name="generator" content="DEstrella.mx">
</head>
<body>
	<header>
		<h1><a href="{$uri}{$trailing}"><svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 3000 1970" aria-hidden="true"><path d="M468 1938a707 707 0 01-303-187A545 545 0 012 1451c-6-65 11-146 33-160 6-3 66 49 134 117 182 184 305 239 532 240 100 0 143-6 212-27a732 732 0 00472-518c38-129 40-242 8-368a498 498 0 00-132-250c-149-163-383-191-660-78-136 56-196 66-299 47C155 426 46 351 13 251-9 191-1 0 21 0c5 0 18 19 31 43s42 52 68 65c70 36 144 30 304-26 125-44 152-50 253-50 126-1 217 19 346 79a972 972 0 01429 376c22 39 44 72 47 72 4 0 25-33 48-72a972 972 0 01429-376c128-60 220-80 345-79 101 0 129 6 254 50 159 56 233 62 304 26 26-13 54-41 67-65 14-24 27-43 31-43 11 0 23 79 23 156 0 180-154 300-387 301-86 2-107-3-216-48-274-115-511-87-660 76a498 498 0 00-132 250c-22 89-28 240-12 300 6 24 10 20 23-29 20-75 117-172 198-200 90-31 153-26 244 18 65 32 93 38 162 40 95 0 140-22 190-90l30-40v56c0 120-34 224-100 291-99 106-218 117-411 37-86-35-94-37-162-25-55 11-78 22-105 50l-32 36 35 69a736 736 0 00456 385c79 23 288 19 373-6 132-39 207-88 336-219 68-68 127-120 133-117 23 14 39 95 33 160-9 94-70 205-163 300a730 730 0 01-320 192c-76 22-105 25-220 19-165-9-290-40-434-111a746 746 0 01-312-288c-23-41-44-73-48-73-3 0-25 32-47 73-135 238-416 386-757 397-127 5-152 3-227-22z"/></svg> Daniel Estrella</a></h1>
		<nav><a href="{$uri}/p/acerca-de{$trailing}">Acerca de…</a></nav>
	</header>
	<main>
		{$cuerpo}
	</main>
	<footer>
		{$navantsig}
		DanielEstrella.com — DEstrella.mx
	</footer>
</body>
</html>
HTML;
		$html = minifyhtml($html);
		if(file_put_contents($publicado, $html)!==FALSE):
			echo _r('✓ Entrada "'.$entrada['título'].'" creada con éxito. ('.$publicado.')', __LINE__);
			$reindexar = TRUE;
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
/* PENDIENTE
if($reindexar):
	$paginación = '';
	$porpágina = 30;
	$actual = 1;
	$páginas = ceil($total / $porpágina);
	for($i = 1; $i <= $páginas; $i++):
		$paginación .= '<a href="/archivo/p/'.$i.'/">'.$i.'</a>';
	endfor;
endif;
*/
?>