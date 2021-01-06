<?php
$_ = function ($val){return $val;};
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

	$e['timetag'] = '<time datetime="'.date('c', $t).'" title="'.$e['fechaLarga'].'">'.$fechaLegible.'</time>';

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
	$res =
	'<article'.
	' id="'.$e['t'].'"'.
	' class="mini-'.TIPO[$e['tipo']].'"'.
	'>';
	if($e['tipo'] == 5
	&& !empty($e['contenido'])):
		$lnk = explode('href="', $e['contenido']);
		if(!empty($lnk[1])):
			$lnk = explode('" ', $lnk[1])[0];
			$res .= '<a href="'.$lnk.'" class="lnkext" target="_blank" rel="nofollow">';
		else:
			$res .=
			'<a href="'.$uri.'/'.$e['slug'].TRAILING.'">';
		endif;
	else:
		$res .=
		'<a href="'.$uri.'/'.$e['slug'].TRAILING.'">';
	endif;
	$res .=
	'<h3>'.$e['título'].'</h3>'.
	'<footer>'.$e['timetag'].'</footer>';
	switch($e['tipo']):
		case 5://enlace
			$res .= '<picture><img src="'.$uri.'/img/1f517.svg" alt=""></picture>';
			break;
		case 6://cita
			break;
		case 7://video
			$res .= videotag($e['videos'][0]);
			break;
		default:
			if(!empty($e['fotos'][0]['src'])):
				$res .= imgtag($e['fotos'][0], $e);
			else:
				$res .= '<picture><img src="'.NOFOTO.'" alt=""></picture>';
			endif;
	endswitch;
	$res .= '<p>'.$e['resumen'].'</p></a></article>';

	return $res;
}
function efoto($e)
{
	$foto = [
		'cuerpo'=>'',
		'CSSadicional'=>'',
		'JSadicional'=>''
	];
	$foto['cuerpo'] =
	'<article class="'.TIPO[$e['tipo']].'">';
	if(!empty($e['fotos'][0]['src'])):
		$foto['cuerpo'] .= imgtag($e['fotos'][0], $e);
	else:
		$foto['cuerpo'] .= '<picture><img src="'.NOFOTO.'" alt=""></picture>';
	endif;
	$foto['cuerpo'] .= '<div class="contenido">';
	if($e['título'] != $e['fechaLarga']):
		$foto['cuerpo'] .= '<h2>'.$e['título'].'</h2>';
	else:
		$foto['cuerpo'] .= '<h2 class="nada">'.$e['título'].'</h2>';
	endif;
	$foto['cuerpo'] .=
	contenido($e['resumen'], $e['contenido']).
	'<footer>'.$e['timetag'].'</footer>'.
	'</div></article>';

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
	'<article class="'.TIPO[$e['tipo']].'">';
	if(!empty($e['videos'][0])):
		$video['cuerpo'] .= videotag($e['videos'][0]);
	else:
		$video['cuerpo'] .= '<picture><img src="'.NOFOTO.'" alt=""></picture>';
	endif;
	$video['cuerpo'] .=
	'<div class="contenido">'.
	'<h2>'.$e['título'].'</h2>'.
	contenido($e['resumen'], $e['contenido']).
	'<footer>'.$e['timetag'].'</footer></div></article>';
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
	'<article class="'.TIPO[$e['tipo']].'">'.
	'<div>'.
	contenido($e['resumen'], $e['contenido']).
	'</div>'.
	'<header>'.
	'<h2>'.$e['timetag'].'</h2>'.
	'</header>'.
	'</article>';

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
	'<article class="'.TIPO[$e['tipo']].'">'.
	'<div>'.
	contenido($e['resumen'], $e['contenido']).
	'</div>'.
	'<header>'.
	'<h2>'.$e['timetag'].'</h2>'.
	'</header>'.
	'</article>';

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
	'<article class="'.TIPO[$e['tipo']].'">'.
	contenido($e['resumen'], $e['contenido']).
	'</article>';
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
	'<article class="'.TIPO[$e['tipo']].'">';

	if($e['título'] != $e['fechaLarga']):
		$artículo['cuerpo'] .=
		'<h2>'.$e['título'].'</h2>';
	else:
		$artículo['cuerpo'] .=
		'<h2 class="nada">'.$e['título'].'</h2>';
	endif;

	$artículo['cuerpo'] .=
	'<footer>'.$e['timetag'].'</footer>';

	if(!empty($e['resumen'])
	&& '<p>'.$e['resumen'].'</p>' != $e['contenido']):
		$artículo['cuerpo'] .= '<p>'.$e['resumen'].'</p>';
	endif;

	if(!empty($e['fotos'])):
		$artículo['cuerpo'] .=
		'<div class="fotoDestacada">'.
		imgtag($e['fotos'][0], $e, '100vw').
		'</div>';
		unset($e['fotos'][0]);

		$busca = '[FOTO]';
		foreach($e['fotos'] as $k=>$f):
			$pos = strpos($e['contenido'], $busca);
			if($pos !== false):
				$fotains =  imgtag($f, $e);
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

	$artículo['cuerpo'] .= '</article>';

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
	'<article class="'.TIPO[$e['tipo']].'">';
	if(!empty($e['título'])):
		$cuerpo .=
		'<header>'.
		'<h2>'.$e['título'].'</h2>'.
		$e['timetag'].
		'</header>';
	endif;

	$galería['cuerpo'] .=
	contenido($e['resumen'], $e['contenido']).
	galeriadefotos($e).
	'</article>';

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
	'<article class="'.TIPO[$e['tipo']].'">'.
	'<div class="gslider">'.
	implode(PHP_EOL,$gh).
	'</div><div class="contenido">'.
	'<h2>'.$e['título'].'</h2>'.
	contenido($e['resumen'], $e['contenido']).
	'<footer>'.$e['timetag'].'</footer>'.
	'</div></article>';

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
	$cuerpo .=
	'<div class="gal">'.
	'<input type="radio" id="fot0" class="nada" name="galería">';
	$lasfotos = array_keys($e['fotos']);
	foreach($e['fotos'] as $k => $f):
		$etipo = $e['tipo'];
		$fotant = $fotsig = '';
		if(isset($lasfotos[array_search($k, $lasfotos)-1])):
			$fantid = $lasfotos[array_search($k, $lasfotos)-1];
			$fotant =
			'<label for="fot'.($k).'" class="modal ant">'.
			'&#62298;'.
			'</label>';
		endif;
		if(isset($lasfotos[array_search($k, $lasfotos)+1])):
			$fsigid = $lasfotos[array_search($k, $lasfotos)+1];
			$fotsig =
			'<label for="fot'.($k+2).'" class="modal sig">'.
			'&#62299;'.
			'</label>';
		endif;
		$cuerpo .=
		'<label for="fot'.($k+1).'" tabindex="'.$k.'" class="min">'.
		imgtag($f, $e).
		'</label>';
		$e['tipo'] = 'fs';
		$cuerpo .=
		'<input type="radio" id="fot'.($k+1).'" name="galería" class="selector">'.
		'<div class="visor">'.
		imgtag($f, $e).$fotant.$fotsig.
		'<label for="fot0" class="cerrar">&#62216;</label>'.
		'</div>';
		$e['tipo'] = $etipo;
	endforeach;
	$cuerpo .= '</div>';
	return $cuerpo;
}
?>