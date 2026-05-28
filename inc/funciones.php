<?php
$_ = function ($val){return $val;};
function h($val){
	return htmlspecialchars((string)$val, ENT_QUOTES, 'UTF-8');
}
function mesMinusculas($mes){
	static $meses = [
		'Enero'=>'enero',
		'Febrero'=>'febrero',
		'Marzo'=>'marzo',
		'Abril'=>'abril',
		'Mayo'=>'mayo',
		'Junio'=>'junio',
		'Julio'=>'julio',
		'Agosto'=>'agosto',
		'Septiembre'=>'septiembre',
		'Octubre'=>'octubre',
		'Noviembre'=>'noviembre',
		'Diciembre'=>'diciembre'
	];
	return $meses[$mes] ?? $mes;
}
function normalizaEntrada($e, $t){
	$blank =
	[
	"t"=> 0,
	"tipo"=>0,
	"título"=>'',
	"resumen"=>'',
	"contenido"=>'',
	"ubicación"=>'',
	"idioma"=>'es',
	"fotos"=>[],
	"videos"=>[],
	'inlinecs'=>''
	];
	$e['t'] = $t;
	$e = array_merge($blank, $e);

	//Crea una fecha legible
	$fechaLegible = DÍA[date('N', $e['t'])].', '.date('j', $e['t']).' de '.MES[date('n', $e['t'])].date(' Y', $e['t']).' a las '.date('H:i', $e['t']);
	$e['fechaLarga'] = $fechaLegible.' GMT'.date('P', $e['t']);
	$e['fechaISO'] = date('c', $e['t']);
	$e['fechaArchivo'] = date('j', $e['t']).' de '.mesMinusculas(MES[date('n', $e['t'])]).' de '.date('Y', $e['t']);
	if(date('H:i', $e['t']) != '00:00'):
		$e['fechaArchivo'] .= ', '.date('H:i', $e['t']);
	endif;

	$e['timetag'] = '<time datetime="'.$e['fechaISO'].'" title="'.$e['fechaLarga'].'">'.$fechaLegible.'</time>';

	//Si no existe un título, usa la fecha legible
	$e['título'] = trim($e['título']);
	if(empty($e['título'])):
		$e['título'] = $e['fechaLarga'];
		$e['slug'] = ARC.date('Y/m/dHis', $e['t']);
	else:
		$e['slug'] = ARC.date('Y/m/', $e['t']).slug($e['título']);
	endif;

	//echo '['.$e['t'].'] '.$e['slug'].PHP_EOL;
	return $e;
}
function minifyhtml($html)
{
	$b = ['/\>[^\S ]+/s', '/[^\S ]+\</s', '/\s+/'];
	$r = ['>', '<', ' '];
	return trim(preg_replace($b,$r, $html));
}
function _r($msg, $l){
	$msg .= (MSGHTM?'<br>':PHP_EOL);
	if(DEBUG):
		return '[L-'.$l.'] '.$msg;
	else:
		return $msg;
	endif;
}
function imgtag($f, $e, $ancho = FALSE){
	foreach($f as $attr=>$val):
		if(is_string($val)):
			$f[$attr] = trim($val);
		endif;
	endforeach;

	switch($e['tipo']):
		case 1://Foto
		case 2://Diapositivas
			$sizes = '(max-width:960px) 90vw, calc(90% - 300px)';
			break;
		case 'fs'://Pantalla completa
			$sizes = '100vw';
			break;
		case 3://Galería
			$sizes = '320px';
			break;
		default:
			if(!$ancho):
				$sizes = '320px';
			else:
				$sizes = $ancho;
			endif;
			break;
	endswitch;

	$source = '';
	//elemento "source"

	if(empty($f['set'])):
		//Asume siempre avif, webp y jpg en la misma ruta
		$ancmin = $ancact = 640;
		$ancmax = 3840;
		$cuantos = $f['tan'] / $ancmin;
		$f['set'] = ['avif'=>'', 'webp'=>'', 'jpg'=>''];
		$f['src'] = str_ireplace('.jpg', '', $f['src']);
		for($i = 1; $i <= $cuantos; $i++):
			$sourceimg = empty($f['set']['avif'])?'':', ';
			$sourceimg .= $f['src'].'-'.$ancact;
			$f['set']['avif'] .= $sourceimg.'.avif '.$ancact.'w';
			$f['set']['webp'] .= $sourceimg.'.webp '.$ancact.'w';
			$f['set']['jpg'] .= $sourceimg.'.jpg '.$ancact.'w';

			if($ancact + $ancmin > $ancmax && is_int($cuantos)):
				break;
			elseif($ancact + $ancmin > $f['tan'] && !is_int($cuantos)):
				$sourceimg = empty($f['set']['avif'])?'':', ';
				$sourceimg .= $f['src'].'-'.$f['tan'];
				$f['set']['avif'] .= $sourceimg.'.avif '.$f['tan'].'w';
				$f['set']['webp'] .= $sourceimg.'.webp '.$f['tan'].'w';
				$f['set']['jpg'] .= $sourceimg.'.jpg '.$f['tan'].'w';
			else:
				$ancact += $ancmin;
			endif;
		endfor;
		$f['src'] .= '-'.$f['tan'].'.jpg';
	endif;

	foreach($f['set'] as $formato=>$imgsrc):
		//Formato jpg es sólo último recurso
		if($formato == 'jpg'
		|| empty($imgsrc)):
			continue;
		endif;
		$source .=
		'<source'.
		' type="image/'.$formato.'"'.
		' srcset="'.cloudtohotlink($f['set'][$formato]).'"'.
		' sizes="'.$sizes.'"'.
		'>';
	endforeach;

	//Atributos del elemento img
	$attrs = '';
	if(!empty($f['set']['jpg'])):
		$attrs .=
		' srcset="'.cloudtohotlink($f['set']['jpg']).'"'.
		' sizes="'.$sizes.'"';
	endif;
	if(!empty($f['alt'])):
		$attrs .= ' alt="'.$f['alt'].'"';
	else:
		$attrs .= ' alt=""';
	endif;
	if(!empty($f['tit'])):
		$attrs .= ' title="'.$f['tit'].'"';
	endif;
	if(!empty($f['tan'])):
		$attrs .= ' width="'.$f['tan'].'"';
	endif;
	if(!empty($f['tal'])):
		$attrs .= ' height="'.$f['tal'].'"';
	endif;
	$attrs .= ' loading="lazy"';

	$imgtag = '<img src="'.cloudtohotlink($f['src']).'"'.$attrs.'>';
	if(!empty($source)):
		$imgtag = '<picture>'.$source.$imgtag.'</picture>';
	endif;

	return $imgtag;
}
//Funciona para google drive, onedrive y dropbox
function cloudtohotlink($lnk){
	if(strpos($lnk, 'onedrive.live.com') !== false):
		if(strpos($lnk, 'iframe') !== false):
			preg_match('/src="([^"]+)"/', $lnk, $match);
			$lnk = $match[1];
		endif;
		$quita = ['/embed?'];
		$pone = ['/download?'];
	elseif(strpos($lnk, 'drive.google.com') !== false):
		$quita = ['file/d/', '/view?usp=sharing'];
		$pone = ['uc?id='];
	elseif(strpos($lnk, 'dropbox.com') !== false):
		$quita = ['/s/', '?dl=0', '?dl=1', '?raw=1'];
		$pone = ['/s/dl/'];
	else:
		return $lnk;
	endif;
	return str_replace($quita, $pone, $lnk);
}
function generaResumen($cont){
	if(empty($cont)):
		return '';
	endif;
	$cont = minifyhtml(strip_tags($cont));
	if(strlen($cont) <= 160):
		return $cont;
	endif;
	$cont = explode(' ', $cont);
	$resumen = '';
	foreach($cont as $c):
		if(strlen($resumen.' '.$c) >= 159):
			return trim($resumen).'…';
		else:
			$resumen .= ' '.$c;
		endif;
	endforeach;
}
function gfycat($v)
{
	if(empty($v)
	|| strpos($v, 'gfycat')===FALSE):
		return FALSE;
	endif;
	$vid = explode('ifr/', $v);
	if(!empty($vid[1])):
		$vid = explode("'", $vid[1])[0];
		$anc = explode("width='", $v)[1];
		$anc = explode("'", $anc)[0];
		$alt = explode("height='", $v)[1];
		$alt = explode("'", $alt)[0];
		$v =
		'<video controls autoplay loop muted playsinline'.
		' poster="https://thumbs.gfycat.com/'.$vid.'-poster.jpg"'.
		' tabindex="-1"'.
		' width="'.$anc.'"'.
		' height="'.$alt.'"'.
		'>'.
		'<source '.
		'src="https://giant.gfycat.com/'.$vid.'.webm" '.
		'type="video/webm"'.
		'>'.
		'<source '.
		'src="https://giant.gfycat.com/'.$vid.'.mp4" '.
		'type="video/mp4"'.
		'>'.
		'</video>';
		return $v;
	else:
		return FALSE;
	endif;
}
function videotag($v)
{
	$video = '';
	if(!empty($v['iframe'])):
		if(strpos($v['iframe'], 'gfycat')!==FALSE):
			return gfycat($v['iframe']);
		endif;
		if(strpos($v['iframe'],' load=')===FALSE):
			$v['iframe'] = str_replace('<iframe', '<iframe load="lazy"', $$v['iframe']);
		endif;
		$video .= $v['iframe'];
	elseif(!empty($v['html'][0][0]['src'])):
		$video .= '<video controls>';
		foreach($v['html'][0] as $vid):
			$video .=
			'<source'.
			' src="'.$vid['src'].'"'.
			' type="'.$vid['mime'].'"'.
			'>';
		endforeach;
		$video .= '</video>';
	else:
		$video .= '<img src="'.NOFOTO.'" alt="">';
	endif;
	return $video;
}
function resumenparaindice($e, $uri = URIPAG)
{
	$res = '<article class="preview-entrada">';
	if($e['tipo'] == 5
	&& !empty($e['contenido'])):
		$lnk = explode('href="', $e['contenido']);
		if(!empty($lnk[1])):
			$lnk = explode('" ', $lnk[1])[0];
			$res .= '<a href="'.$lnk.'" target="_blank" rel="nofollow">';
		else:
			$res .= '<a href="'.$uri.'/'.$e['slug'].TRAILING.'">';
		endif;
	else:
		$res .= '<a href="'.$uri.'/'.$e['slug'].TRAILING.'">';
	endif;

	$miniatura = NOFOTO;
	$alt = 'Miniatura de '.$e['título'];
	switch($e['tipo']):
		case 5://enlace
			$miniatura = '/img/1f517.svg';
			$alt = '';
			break;
		default:
			if(!empty($e['fotos'][0]['src'])):
				$miniatura = cloudtohotlink($e['fotos'][0]['src']);
				if(!empty($e['fotos'][0]['alt'])):
					$alt = $e['fotos'][0]['alt'];
				endif;
			endif;
	endswitch;
	$res .=
	'<img src="'.$miniatura.'" alt="'.h($alt).'" loading="lazy">'.
	'<strong>'.h($e['título']).'</strong>'.
	'<time datetime="'.$e['fechaISO'].'">'.$e['fechaArchivo'].'</time>'.
	'</a></article>';

	return $res;
}
function claseEntrada($e)
{
	return 'pagina entrada-'.basename($e['slug']).' tipo-'.TIPO[$e['tipo']];
}
function abreEntrada($e)
{
	return
	'<article class="'.claseEntrada($e).'">'.
	'<h1>'.h($e['título']).'</h1>'.
	'<p class="fecha-entrada">'.$e['timetag'].'</p>'.
	'<section class="texto-columna">';
}
function cierraEntrada()
{
	return '</section></article>';
}
function figuraEntrada($f, $e)
{
	$horizontal = !empty($f['tan']) && !empty($f['tal']) && $f['tan'] >= $f['tal'];
	$claseContenedor = $horizontal ? 'contenedor-imagen imagen-horizontal' : 'contenedor-imagen';
	$figcaption = '';
	if(!empty($f['dsc'])):
		$figcaption = '<figcaption>'.$f['dsc'].'</figcaption>';
	elseif(!empty($f['tit'])):
		$figcaption = '<figcaption>'.$f['tit'].'</figcaption>';
	endif;
	return
	'<figure class="imagen-columna'.($horizontal?' imagen-relato-horizontal':'').'">'.
	'<div class="'.$claseContenedor.'">'.
	imgtag($f, $e).
	'</div>'.
	$figcaption.
	'</figure>';
}
function efoto($e)
{
	$foto = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$foto['cuerpo'] =
	abreEntrada($e);
	if(!empty($e['fotos'][0]['src'])):
		$foto['cuerpo'] .= figuraEntrada($e['fotos'][0], $e);
	else:
		$foto['cuerpo'] .= '<figure class="imagen-columna"><div class="contenedor-imagen"><img src="'.NOFOTO.'" alt="" loading="lazy"></div></figure>';
	endif;
	$foto['cuerpo'] .=
	contenido($e['resumen'], $e['contenido']).
	cierraEntrada();

	return $foto;
}
function evideo($e)
{
	$video = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$video['cuerpo'] =
	abreEntrada($e);
	if(!empty($e['videos'][0])):
		$video['cuerpo'] .= '<figure class="imagen-columna"><div class="contenedor-imagen imagen-horizontal">'.videotag($e['videos'][0]).'</div></figure>';
	else:
		$video['cuerpo'] .= '<figure class="imagen-columna"><div class="contenedor-imagen"><img src="'.NOFOTO.'" alt="" loading="lazy"></div></figure>';
	endif;
	$video['cuerpo'] .=
	contenido($e['resumen'], $e['contenido']).
	cierraEntrada();
	return $video;
}
function eestado($e)
{
	$estado = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$estado['cuerpo'] =
	abreEntrada($e).
	contenido($e['resumen'], $e['contenido']).
	cierraEntrada();

	return $estado;
}
function eenlace($e)
{
	$enlace = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$enlace['cuerpo'] =
	abreEntrada($e).
	contenido($e['resumen'], $e['contenido']).
	cierraEntrada();

	return $enlace;
}
function ecita($e)
{
	$cita = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$cita['cuerpo'] =
	abreEntrada($e).
	contenido($e['resumen'], $e['contenido']).
	cierraEntrada();
	return $cita;
}
function eartículo($e)
{
	$artículo = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$artículo['cuerpo'] =
	abreEntrada($e);

	if(!empty($e['resumen'])
	&& '<p>'.$e['resumen'].'</p>' != $e['contenido']):
		$artículo['cuerpo'] .= '<p>'.$e['resumen'].'</p>';
	endif;

	if(!empty($e['fotos'])):
		$artículo['cuerpo'] .=
		figuraEntrada($e['fotos'][0], $e);
		unset($e['fotos'][0]);

		$busca = '[FOTO]';
		foreach($e['fotos'] as $k=>$f):
			$pos = strpos($e['contenido'], $busca);
			if($pos !== false):
				$fotains =  figuraEntrada($f, $e);
				$e['contenido'] = substr_replace($e['contenido'], $fotains, $pos, strlen($busca));
				unset($e['fotos'][$k]);
			else:
				break;
			endif;
		endforeach;
		$artículo['cuerpo'] .=
		str_replace($busca, '', $e['contenido']);
	else:
		$artículo['cuerpo'] .= $e['contenido'];
	endif;
	//Si aún quedan fotos, crea una galería
	if(!empty($e['fotos'])):
		$artículo['cuerpo'] .= galeriadefotos($e);
	endif;

	$artículo['cuerpo'] .= cierraEntrada();

	return $artículo;
}
function egalería($e)
{
	$galería = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$galería['cuerpo'] =
	abreEntrada($e).
	contenido($e['resumen'], $e['contenido']).
	galeriadefotos($e).
	cierraEntrada();

	return $galería;
}
function ediapositivas($e, $autoplay = FALSE)
{
	$diapositivas = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	//duración de las animaciones en milisegundos
	$durfxms = 32000;
	//retraso en las animaciones en milisegundos
	$retfxms = -2000;
	//CSS
	$gc = [];
	$gc['bprevnext'] = '';
	$gc['bplayprevnext'] = '';
	$gc['bplaypause'] = '';
	$gc['bplaypausefx'] = '';
	$gc['fxin'] = '';
	$gc['pointin'] = '.gslider > .cs_bullets > label:hover > .cs_point,';
	$gc['bullplaypausefx'] = '';
	$gc['dscvisible'] = '';
	$gc['dscvisiblewrap'] = '';
	$gc['dscfx'] = '';
	//HTML
	$gh = [];
	$gh['ancslides'] = '';
	$gh['ancplay'] =
	'<input'.
	' name="cs_anchor1"'.
	' id="cs_play1"'.
	' type="radio"'.
	' class="cs_anchor"'.
	($autoplay?' checked':'').
	'>';
	$gh['ancpause'] = '';
	$gh['imgslide'] =
	'<ul>'.
	'<li class="cs_skeleton">';
	//Busca la imagen más alta
	$másalta = ['dim'=>0, 'idx'=>false];
	foreach($e['fotos'] as $k=>$f):
		if($f['tal'] > $másalta['dim']):
			$másalta['idx'] = $k;
		endif;
	endforeach;
	//Define el alto de las diapositivas
	if(!empty($e['fotos'][$másalta['idx']]['tal'])):
		$gh['imgslide'] .=
		imgtag($e['fotos'][$másalta['idx']], $e);
	else:
		$gh['imgslide'] .=
		'<picture><img src="'.NOFOTO.'" alt=""></picture>';
	endif;
	$gh['imgslide'] .=
	'</li>';
	$gh['description'] = '<div class="cs_description">';
	$gh['bplaypause'] =
	'<div class="cs_play_pause">'.
	'<label class="cs_play" for="cs_play1">'.
	'<span><i></i><b></b></span>'.
	'</label>';
	$gh['bprev'] = '<div class="cs_arrowprev">';
	$gh['bnext'] = '<div class="cs_arrownext">';
	$gh['bullets'] = '<div class="cs_bullets">';
	foreach($e['fotos'] as $k=>$f):
		//Cuerpo diapositivas CSS
		if(isset($e['fotos'][$k-1])):
			$sprev = $k-1;
		else:
			$sprev = array_key_last($e['fotos']);
		endif;
		if(isset($e['fotos'][$k+1])):
			$snext = $k+1;
		else:
			$snext = array_key_first($e['fotos']);
		endif;
		$gc['bprevnext'] .=
		".gslider > #cs_slide1_{$k}:checked ~ .cs_arrowprev > label.num{$sprev},".
		".gslider > #cs_pause1_{$k}:checked ~ .cs_arrowprev > label.num{$sprev},".
		".gslider > #cs_slide1_{$k}:checked ~ .cs_arrownext > label.num{$snext},".
		".gslider > #cs_pause1_{$k}:checked ~ .cs_arrownext > label.num{$snext},";
		$gc['bplayprevnext'] .=
		'.gslider > #cs_play1:checked ~ .cs_arrowprev > label.num'.$sprev.','.
		'.gslider > #cs_play1:checked ~ .cs_arrownext > label.num'.$snext.
		'{animation:arrow1 '.$durfxms.'ms infinite '.$retfxms.'ms}';
		$gc['bplaypause'] .= '.gslider > #cs_play1:checked ~ .cs_play_pause > .cs_pause.num'.$k.'{animation:pauseChange1 '.$durfxms.'ms infinite '.$retfxms.'ms;opacity:0;z-index:-1}';
		$gc['bplaypausefx'] .=
		".gslider > #cs_slide1_{$k}:checked ~ ul > .slide.num{$k},".
		".gslider > #cs_pause1_{$k}:checked ~ ul > .slide.num{$k},";
		$gc['fxin'] .= '.gslider > #cs_play1:checked ~ ul > .slide.num'.$k.'{animation:fade-in1 '.$durfxms.'ms infinite '.$retfxms.'ms}';
		$gc['pointin'] .=
		".gslider > #cs_slide1_{$k}:checked ~ .cs_bullets > label.num{$k} > .cs_point,".
		".gslider > #cs_pause1_{$k}:checked ~ .cs_bullets > label.num{$k} > .cs_point,";
		$gc['bullplaypausefx'] .=
		'.gslider > #cs_play1:checked ~ .cs_bullets > label.num'.$k.' > .cs_point,'.
		'.gslider > #cs_pause1:checked ~ .cs_bullets > label.num'.$k.' > .cs_point'.
		'{animation: bullet1 '.$durfxms.'ms infinite '.$retfxms.'ms}';
		$gc['dscvisible'] .=
		".gslider > #cs_slide1_{$k}:checked ~ .cs_description > .num{$k} > .cs_title,".
		".gslider > #cs_slide1_{$k}:checked ~ .cs_description > .num{$k} > .cs_descr,".
		".gslider > #cs_pause1_{$k}:checked ~ .cs_description > .num{$k} > .cs_title,".
		".gslider > #cs_pause1_{$k}:checked ~ .cs_description > .num{$k} > .cs_descr,";
		$gc['dscvisiblewrap'] .=
		".gslider > #cs_slide1_{$k}:checked ~ .cs_description > .num{$k} .cs_wrapper,".
		".gslider > #cs_pause1_{$k}:checked ~ .cs_description > .num{$k} .cs_wrapper,";
		if(!empty($f['tit'])):
			$gc['dscfx'] .=
			'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' > .cs_title'.
			'{animation: cs_title1 '.$durfxms.'ms infinite '.($retfxms+600).'ms ease}'.
			'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' .cs_title > .cs_wrapper'.
			'{animation: cs_title_text1 '.$durfxms.'ms infinite '.($retfxms+750).'ms ease}';
		endif;
		if(!empty($f['dsc'])):
			$gc['dscfx'] .=
			'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' > .cs_descr'.
			'{animation: cs_descr1 '.$durfxms.'ms infinite '.($retfxms+850).'ms ease}'.
			'.gslider > #cs_play1:checked ~ .cs_description > .num'.$k.' .cs_descr > .cs_wrapper'.
			'{animation: cs_descr_text1 '.$durfxms.'ms infinite '.($retfxms+1000).'ms ease}';
		endif;
		$retfxms += 8000;
		//Diapositivas HTML Cuerpo
		$gh['ancslides'] .=
		'<input name ="cs_anchor1" id="cs_slide1_'.$k.'" type="radio" class="cs_anchor slide">';
		if($k==0 && !$autoplay):
			$checked = ' checked';
		else:
			$checked = '';
		endif;
		$gh['ancpause'] .=
		'<input name ="cs_anchor1" id="cs_pause1_'.$k.'" type="radio" class="cs_anchor pause"'.$checked.'>';
		$gh['imgslide'] .=
		'<li class="num'.$k.' img slide">'.
		imgtag($f, $e).
		'</li>';
		//Incluye título y descripción si existen
		if(!empty($f['tit']) || !empty($f['dsc'])):
			$gh['description'] .= '<label class="num'.$k.'">';
		endif;
		if(!empty($f['tit'])):
			$gh['description'] .=
			'<span class="cs_title">'.
			'<span class="cs_wrapper">'.$f['tit'].'</span>'.
			'</span>';
		endif;
		if(!empty($f['dsc'])):
			$gh['description'] .=
			'<span class="cs_descr">'.
			'<span class="cs_wrapper">'.$f['dsc'].'</span>'.
			'</span>';
		endif;
		if(!empty($f['tit']) || !empty($f['dsc'])):
			$gh['description'] .= '</label>';
		endif;
		$gh['bplaypause'] .=
		'<label'.
		' class="cs_pause num'.$k.'"'.
		' for="cs_pause1_'.$k.'"'.
		'>'.
		'<span><i></i><b></b></span>'.
		'</label>';
		//¿¿bprev y bnext son lo mismo?? 🤔
		$gh['bprev'] .=
		'<label class="num'.$k.'" for="cs_slide1_'.$k.'">'.
		'<span><i></i><b></b></span>'.
		'</label>';
		$gh['bnext'] .=
		'<label class="num'.$k.'" for="cs_slide1_'.$k.'">'.
		'<span><i></i><b></b></span>'.
		'</label>';
		$gh['bullets'] .=
		'<label class="num'.$k.'" for="cs_slide1_'.$k.'">'.
		'<span class="cs_point"></span>'.
		'</label>';
	endforeach;
	//Diapositivas CSS Fin
	$gc['bprevnext'] = rtrim($gc['bprevnext'],',').'{opacity:1;z-index:5}';
	$gc['bplaypausefx'] .= rtrim($gc['bplaypausefx'],',').'{opacity:1;z-index:2;transform:scale(1)}';
	$gc['pointin'] .= rtrim($gc['pointin'],',').'{background:#FFF}';
	$gc['dscvisible'] .= rtrim($gc['dscvisible'],',').'{opacity:1;visibility:visible;transform:translateY(0)}';
	$gc['dscvisiblewrap'] .= rtrim($gc['dscvisiblewrap'],',').'{opacity:1;transform:translateY(0)}';
	//Diapositivas HTML Fin
	$gh['imgslide'] .= '</ul>';
	$gh['description'] .= '</div>';
	$gh['bplaypause'] .= '</div>';
	$gh['bprev'] .= '</div>';
	$gh['bnext'] .= '</div>';
	$gh['bullets'] .= '</div>';

	//Error al darle play, desactivado por el momento
	$gh['bplaypause']='';

	$diapositivas['CSSadicional'] =
	'<link'.
	' rel="stylesheet"'.
	' href="'.URI.'/css/slider'.(DEBUG?'':'.min').'.css"'.
	'>'.
	'<style>'.implode(PHP_EOL,$gc).'</style>';

	$diapositivas['cuerpo'] =
	abreEntrada($e).
	'<div class="gslider">'.
	implode(PHP_EOL,$gh).
	'</div>'.
	contenido($e['resumen'], $e['contenido']).
	cierraEntrada();

	return $diapositivas;
}
function contenido($resumen, $contenido)
{
	if(!empty($resumen)
	&& '<p>'.$resumen.'</p>' != $contenido):
		return '<p>'.$resumen.'</p>'.$contenido;
	else:
		return $contenido;
	endif;
}
function galeriadefotos($e)
{
	$cuerpo = '<div class="galeria-imagenes" data-lightbox-gallery>';
	foreach($e['fotos'] as $k => $f):
		$src = cloudtohotlink($f['src']);
		$alt = !empty($f['alt']) ? $f['alt'] : '';
		$caption = '';
		if(!empty($f['dsc'])):
			$caption = '<figcaption>'.$f['dsc'].'</figcaption>';
		elseif(!empty($f['tit'])):
			$caption = '<figcaption>'.$f['tit'].'</figcaption>';
		endif;
		$cuerpo .=
		'<figure>'.
		'<a href="'.$src.'" class="enlace-galeria">'.
		'<img src="'.$src.'" alt="'.h($alt).'" loading="lazy">'.
		'</a>'.
		$caption.
		'</figure>';
	endforeach;
	$cuerpo .= '</div>';
	return $cuerpo;
}
?>
