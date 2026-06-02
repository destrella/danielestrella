#!/usr/bin/env node
const fs = require("fs");
const path = require("path");
const { execFileSync } = require("child_process");

const ROOT = path.resolve(__dirname, "../..");
const DOMAIN = "https://danielestrella.com";
const TZ = "America/Merida";
const MEDIA_ROOT_REL = "tmp/instagram-import/media";
const CSV_REL = "tmp/instagram-import/registro-medios-instagram.csv";
const ZIP_NAMES = [
  "destrella.mx_20201214_part_2.zip",
  "destrella.mx_20201214_part_3.zip",
  "destrella.mx_20201214_part_4.zip",
];

process.chdir(ROOT);

const homeHtml = fs.readFileSync("index.html", "utf8");
const logo = (homeHtml.match(/<svg\b[\s\S]*?<\/svg>/) || [""])[0];
const cssHref = ((fs.readFileSync("archivo/index.html", "utf8").match(/<link rel="stylesheet" href="([^"]*\/css\/estilos\.css\?v=[^"]+)"/) || [])[1] || "/css/estilos.css?v=202605302205").replace(DOMAIN, "");
const jsSrc = ((fs.readFileSync("archivo/index.html", "utf8").match(/<script src="([^"]*\/js\/scripts\.js\?v=[^"]+)"/) || [])[1] || "/js/scripts.js?v=202605292315").replace(DOMAIN, "");

const pad = (value) => String(value).padStart(2, "0");
const escapeHtml = (value) => String(value ?? "")
  .replace(/&/g, "&amp;")
  .replace(/</g, "&lt;")
  .replace(/>/g, "&gt;");
const escapeAttr = (value) => escapeHtml(value).replace(/"/g, "&quot;");
const decodeEntities = (value) => String(value ?? "")
  .replace(/&#(\d+);/g, (_, code) => String.fromCodePoint(Number(code)))
  .replace(/&#x([0-9a-f]+);/gi, (_, code) => String.fromCodePoint(parseInt(code, 16)))
  .replace(/&quot;/g, "\"")
  .replace(/&#039;/g, "'")
  .replace(/&apos;/g, "'")
  .replace(/&lt;/g, "<")
  .replace(/&gt;/g, ">")
  .replace(/&amp;/g, "&");
const stripTags = (value) => decodeEntities(String(value ?? "").replace(/<[^>]*>/g, " ")).replace(/\s+/g, " ").trim();
const stripHtml = stripTags;

function intlParts(date) {
  const formatter = new Intl.DateTimeFormat("en-CA", {
    timeZone: TZ,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hourCycle: "h23",
  });
  return Object.fromEntries(formatter.formatToParts(date)
    .filter((part) => part.type !== "literal")
    .map((part) => [part.type, part.value]));
}

function offsetMinutes(date) {
  const parts = intlParts(date);
  const localAsUTC = Date.UTC(
    Number(parts.year),
    Number(parts.month) - 1,
    Number(parts.day),
    Number(parts.hour),
    Number(parts.minute),
    Number(parts.second),
  );
  return Math.round((localAsUTC - date.getTime()) / 60000);
}

function localInfo(date) {
  const parts = intlParts(date);
  const offset = offsetMinutes(date);
  const sign = offset >= 0 ? "+" : "-";
  const abs = Math.abs(offset);
  const offsetText = `${sign}${pad(Math.floor(abs / 60))}:${pad(abs % 60)}`;
  const weekday = new Intl.DateTimeFormat("es-MX", { timeZone: TZ, weekday: "long" }).format(date);
  const month = new Intl.DateTimeFormat("es-MX", { timeZone: TZ, month: "long" }).format(date);
  const cap = (text) => text.charAt(0).toUpperCase() + text.slice(1);
  const fechaLegible = `${cap(weekday)}, ${Number(parts.day)} de ${cap(month)} ${parts.year} a las ${parts.hour}:${parts.minute}`;
  const fechaArchivo = `${Number(parts.day)} de ${month} de ${parts.year}, ${parts.hour}:${parts.minute}`;
  return {
    ...parts,
    iso: `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}:${parts.second}${offsetText}`,
    key: `${parts.year}-${parts.month}-${parts.day}T${parts.hour}:${parts.minute}`,
    ymdhms: `${parts.day}${parts.hour}${parts.minute}${parts.second}`,
    fechaLegible,
    fechaArchivo,
    offsetText,
  };
}

function cleanCaption(caption) {
  return String(caption ?? "")
    .replace(/\r\n?/g, "\n")
    .replace(/[ \t]+\n/g, "\n")
    .trim();
}

function titleFromCaption(caption, local) {
  const cleaned = cleanCaption(caption);
  const candidate = cleaned
    .split("\n")
    .map((line) => line.trim())
    .find((line) => line && line !== ".") || "";
  let title = candidate.replace(/https?:\/\/\S+/g, "").replace(/\s+/g, " ").trim();
  if (!/[\p{L}\p{N}]/u.test(title)) {
    return local.fechaLegible;
  }
  if (title.length > 86) {
    const clipped = title.slice(0, 86);
    const lastSpace = clipped.lastIndexOf(" ");
    title = `${(lastSpace > 45 ? clipped.slice(0, lastSpace) : clipped).trim()}...`;
  }
  return title;
}

function summaryFromCaption(caption, local, location) {
  let text = cleanCaption(caption).replace(/\s+/g, " ").trim();
  if (!text) {
    text = location
      ? `Publicacion de Instagram en ${location}, ${local.fechaArchivo}.`
      : `Publicacion de Instagram del ${local.fechaArchivo}.`;
  }
  if (text.length > 160) {
    const clipped = text.slice(0, 159);
    const lastSpace = clipped.lastIndexOf(" ");
    text = `${(lastSpace > 80 ? clipped.slice(0, lastSpace) : clipped).trim()}...`;
  }
  return text;
}

function slugify(value) {
  return String(value ?? "")
    .normalize("NFD")
    .replace(/[\u0300-\u036f]/g, "")
    .toLowerCase()
    .replace(/&/g, " y ")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "")
    .slice(0, 90)
    .replace(/-+$/g, "");
}

function linkify(text) {
  const withLinks = escapeHtml(text).replace(/(https?:\/\/[^\s<]+)/g, (url) => {
    const clean = url.replace(/[),.;!?]+$/g, "");
    const tail = url.slice(clean.length);
    return `<a href="${escapeAttr(clean)}" target="_blank" rel="noopener nofollow">${escapeHtml(clean)}</a>${escapeHtml(tail)}`;
  });
  return linkMentions(withLinks);
}

function linkMentions(html) {
  return String(html).split(/(<a\b[^>]*>[\s\S]*?<\/a>)/gi).map((chunk) => {
    if (/^<a\b/i.test(chunk)) {
      return chunk;
    }
    return chunk.replace(/(^|[^A-Za-z0-9_./])@([A-Za-z0-9_](?:[A-Za-z0-9._]{0,28}[A-Za-z0-9_])?)/g, (_, prefix, user) => {
      const url = `https://www.instagram.com/${user}`;
      return `${prefix}<a href="${url}" target="_blank" rel="noopener nofollow">@${user}</a>`;
    });
  }).join("");
}

function captionHtml(caption, location) {
  const lines = cleanCaption(caption).split("\n");
  const blocks = [];
  let current = [];
  for (const raw of lines) {
    const line = raw.trim();
    if (!line || line === ".") {
      if (current.length) {
        blocks.push(current);
        current = [];
      }
      continue;
    }
    current.push(line);
  }
  if (current.length) {
    blocks.push(current);
  }
  const html = blocks.map((block) => `\t\t\t\t<p>${block.map(linkify).join("<br>")}</p>`).join("\n");
  const locationHtml = location ? `\n\t\t\t\t<p><small>Ubicacion: ${linkify(location)}</small></p>` : "";
  return `${html}${locationHtml}`;
}

function walk(dir, out = []) {
  if (!fs.existsSync(dir)) return out;
  for (const name of fs.readdirSync(dir)) {
    const full = path.join(dir, name);
    const stat = fs.statSync(full);
    if (stat.isDirectory()) {
      walk(full, out);
    } else if (name === "index.html") {
      out.push(full.replaceAll(path.sep, "/"));
    }
  }
  return out;
}

function parseEntry(file) {
  const html = fs.readFileSync(file, "utf8");
  const timeIso = (html.match(/article:published_time" content="([^"]+)"/) || html.match(/<time datetime="([^"]+)"/) || [])[1];
  if (!timeIso) return null;
  const titleHtml = (html.match(/<h1>([\s\S]*?)<\/h1>/) || [])[1] || "";
  const title = stripTags(titleHtml);
  const slug = path.dirname(file).replaceAll(path.sep, "/");
  return {
    file,
    html,
    title,
    slug,
    timeIso,
    timeKey: timeIso.slice(0, 16),
  };
}

function articleHtml(html) {
  return (html.match(/<article\b[\s\S]*?<\/article>/) || [""])[0];
}

function countArticleMedia(html) {
  const article = articleHtml(html);
  const imageCount = [...article.matchAll(/<img\b[^>]*\bsrc="([^"]+)"/g)]
    .filter((match) => !match[1].includes("/img/imagen-rota.png") && !match[1].includes("/portada.webp")).length;
  const videoCount = [...article.matchAll(/<video\b/g)].length;
  return imageCount + videoCount;
}

function firstArticleImage(html) {
  const article = articleHtml(html);
  for (const match of article.matchAll(/<img\b[^>]*\bsrc="([^"]+)"/g)) {
    const src = match[1];
    if (!src.includes("/img/imagen-rota.png") && !src.includes("/portada.webp")) {
      return src;
    }
  }
  return "";
}

function isImage(item) {
  return /\.(jpe?g|png|webp|gif)$/i.test(item.path);
}

function mimeFor(file) {
  const ext = path.extname(file).toLowerCase();
  if (ext === ".mp4") return "video/mp4";
  if (ext === ".webm") return "video/webm";
  if (ext === ".png") return "image/png";
  if (ext === ".webp") return "image/webp";
  if (ext === ".gif") return "image/gif";
  return "image/jpeg";
}

function imageSize(buffer) {
  if (buffer.length >= 24 && buffer.slice(0, 8).equals(Buffer.from([0x89, 0x50, 0x4e, 0x47, 0x0d, 0x0a, 0x1a, 0x0a]))) {
    return { width: buffer.readUInt32BE(16), height: buffer.readUInt32BE(20) };
  }
  if (buffer[0] !== 0xff || buffer[1] !== 0xd8) return null;
  let offset = 2;
  while (offset < buffer.length) {
    if (buffer[offset] !== 0xff) return null;
    const marker = buffer[offset + 1];
    const length = buffer.readUInt16BE(offset + 2);
    if ([0xc0, 0xc1, 0xc2, 0xc3, 0xc5, 0xc6, 0xc7, 0xc9, 0xca, 0xcb, 0xcd, 0xce, 0xcf].includes(marker)) {
      return { height: buffer.readUInt16BE(offset + 5), width: buffer.readUInt16BE(offset + 7) };
    }
    offset += 2 + length;
  }
  return null;
}

function loadInstagramItems() {
  const items = [];
  const counts = { photos: 0, videos: 0, stories: 0, profile: 0 };
  for (const zipName of ZIP_NAMES) {
    if (!fs.existsSync(zipName)) {
      throw new Error(`No existe ${zipName}`);
    }
    const raw = execFileSync("unzip", ["-p", zipName, "media.json"], {
      encoding: "utf8",
      maxBuffer: 250 * 1024 * 1024,
    });
    const data = JSON.parse(raw);
    for (const key of Object.keys(counts)) {
      counts[key] += Array.isArray(data[key]) ? data[key].length : 0;
    }
    for (const type of ["photos", "videos"]) {
      for (const item of data[type] || []) {
        items.push({ ...item, sourceType: type, zipName });
      }
    }
  }
  items.sort((a, b) => Date.parse(a.taken_at) - Date.parse(b.taken_at));
  return { items, counts };
}

function groupItems(items) {
  const groups = [];
  for (const item of items) {
    const last = groups[groups.length - 1];
    const key = `${cleanCaption(item.caption)}\u0000${item.location || ""}`;
    const time = Date.parse(item.taken_at);
    if (last && last.key === key && Math.abs(time - last.lastTime) <= 120000) {
      last.items.push(item);
      last.lastTime = time;
      continue;
    }
    groups.push({
      key,
      firstTime: time,
      lastTime: time,
      caption: cleanCaption(item.caption),
      location: item.location || "",
      items: [item],
    });
  }
  return groups;
}

function uniqueSlug(title, local, usedSlugs) {
  const titleSlug = slugify(title);
  const monthRoot = `archivo/${local.year}/${local.month}`;
  let candidate = titleSlug ? `${monthRoot}/${titleSlug}` : `${monthRoot}/${local.ymdhms}`;
  if (!usedSlugs.has(candidate)) {
    usedSlugs.add(candidate);
    return candidate;
  }
  candidate = titleSlug ? `${monthRoot}/${titleSlug}-${local.ymdhms}` : `${monthRoot}/${local.ymdhms}-2`;
  let suffix = 2;
  while (usedSlugs.has(candidate)) {
    suffix += 1;
    candidate = titleSlug ? `${monthRoot}/${titleSlug}-${local.ymdhms}-${suffix}` : `${monthRoot}/${local.ymdhms}-${suffix}`;
  }
  usedSlugs.add(candidate);
  return candidate;
}

function copyMedia(item, slug, index) {
  const filename = `${pad(index + 1)}-${path.basename(item.path)}`;
  const mediaSlug = slug.replace(/^archivo\//, "");
  const destRel = path.posix.join(MEDIA_ROOT_REL, mediaSlug, filename);
  const destAbs = path.join(ROOT, destRel);
  fs.mkdirSync(path.dirname(destAbs), { recursive: true });
  let buffer;
  if (fs.existsSync(destAbs)) {
    buffer = fs.readFileSync(destAbs);
  } else {
    buffer = execFileSync("unzip", ["-p", item.zipName, item.path], {
      maxBuffer: 500 * 1024 * 1024,
    });
    fs.writeFileSync(destAbs, buffer);
  }
  const size = isImage(item) ? imageSize(buffer) : null;
  return {
    source: item.path,
    rel: destRel,
    url: `/${destRel}`,
    kind: isImage(item) ? "image" : "video",
    mime: mimeFor(item.path),
    size,
  };
}

function figureImage(media, title, caption, index, gallery = false) {
	const altBase = /[\p{L}\p{N}]/u.test(caption) ? caption : `Imagen de ${title}`;
	const alt = mediaCountSafeAlt(altBase, index);
	const dims = media.size ? ` width="${media.size.width}" height="${media.size.height}"` : "";
	const aspectClass = aspectClassForMedia(media);
	const imgClass = aspectClass ? ` class="${aspectClass}"` : "";
	const img = `<img src="${escapeAttr(media.url)}"${imgClass} alt="${escapeAttr(alt)}"${dims} loading="lazy">`;
	if (gallery) {
		return `\t\t\t\t\t<figure>\n\t\t\t\t\t\t<a href="${escapeAttr(media.url)}" class="enlace-galeria">${img}</a>\n\t\t\t\t\t</figure>`;
	}
	const wide = aspectClass === "imagen-horizontal" || aspectClass === "imagen-panoramica";
	return `\t\t\t\t<figure class="imagen-columna${wide ? " imagen-relato-horizontal" : ""}">\n\t\t\t\t\t<div class="contenedor-imagen${aspectClass ? ` ${aspectClass}` : ""}">\n\t\t\t\t\t\t${img}\n\t\t\t\t\t</div>\n\t\t\t\t</figure>`;
}

function mediaCountSafeAlt(text, index) {
  const clean = String(text).replace(/\s+/g, " ").trim();
  const base = clean.length > 120 ? `${clean.slice(0, 117).trim()}...` : clean;
	return index > 0 ? `${base} (${index + 1})` : base;
}

function aspectClassForMedia(media) {
	if (!media.size || !media.size.width || !media.size.height) {
		return "";
	}
	const ratio = media.size.width / media.size.height;
	if (ratio >= 1.85) {
		return "imagen-panoramica";
	}
	if (ratio > 1.08) {
		return "imagen-horizontal";
	}
	if (ratio < 0.82) {
		return "imagen-vertical";
	}
	return "imagen-cuadrada";
}

function figureVideo(media, title, index, gallery = false) {
  const html = `<video controls preload="metadata"><source src="${escapeAttr(media.url)}" type="${escapeAttr(media.mime)}"></video>`;
  const figcaption = linkify(index > 0 ? `${title} (${index + 1})` : title);
  const fig = `<figure class="imagen-columna imagen-relato-horizontal">\n\t\t\t\t\t<div class="contenedor-imagen imagen-horizontal">\n\t\t\t\t\t\t${html}\n\t\t\t\t\t</div>\n\t\t\t\t\t<figcaption>${figcaption}</figcaption>\n\t\t\t\t</figure>`;
  return gallery ? `\t\t\t\t\t${fig.replace(/\n/g, "\n\t\t\t\t\t")}` : `\t\t\t\t${fig}`;
}

function mediaBlock(group, onlyMedia = group.media) {
  const media = onlyMedia.filter((item) => !item.missing);
  if (!media.length) {
    return `\t\t\t\t<figure class="imagen-columna"><div class="contenedor-imagen"><img src="/img/imagen-rota.png" alt="" loading="lazy"></div></figure>`;
  }
  if (media.length === 1) {
    const item = media[0];
    return item.kind === "image"
      ? figureImage(item, group.title, group.caption, 0)
      : figureVideo(item, group.title, 0);
  }
  const figures = media.map((item, index) => item.kind === "image"
    ? figureImage(item, group.title, group.caption, index, true)
    : figureVideo(item, group.title, index, true)).join("\n");
  return `\t\t\t\t<div class="galeria-imagenes" data-lightbox-gallery>\n${figures}\n\t\t\t\t</div>`;
}

function renderEntry(group) {
  const local = group.local;
  const title = escapeHtml(group.title);
  const visibleTitle = linkify(group.title);
  const titleAttr = escapeAttr(group.title);
  const summary = escapeAttr(group.summary);
  const firstImage = group.media.find((item) => item.kind === "image" && !item.missing);
  const ogImage = firstImage ? `${DOMAIN}${firstImage.url}` : `${DOMAIN}/portada.webp`;
  const bodyText = captionHtml(group.caption, group.location);
  const content = bodyText ? `${mediaBlock(group)}\n${bodyText}` : mediaBlock(group);
  return `<!DOCTYPE html>
<html lang="es">
<head>
\t<meta charset="UTF-8">
\t<meta name="viewport" content="width=device-width, initial-scale=1.0">
\t<title>${title} — Daniel Estrella</title>
\t<meta name="description" content="${summary}">
\t<meta name="author" content="Daniel Estrella">
\t<meta name="robots" content="index,follow,max-image-preview:large">
\t<link rel="canonical" href="${DOMAIN}/${group.slug}/">
\t<meta property="og:locale" content="es_MX">
\t<meta property="og:site_name" content="Daniel Estrella">
\t<meta property="og:type" content="article">
\t<meta property="og:title" content="${titleAttr} — Daniel Estrella">
\t<meta property="og:description" content="${summary}">
\t<meta property="og:url" content="${DOMAIN}/${group.slug}/">
\t<meta property="og:image" content="${escapeAttr(ogImage)}">
\t<meta property="article:published_time" content="${local.iso}">
\t<meta property="article:author" content="Daniel Estrella">
\t<meta property="article:section" content="Archivo ${local.year}">
\t<meta name="twitter:card" content="summary_large_image">
\t<meta name="twitter:title" content="${titleAttr} — Daniel Estrella">
\t<meta name="twitter:description" content="${summary}">
\t<meta name="twitter:image" content="${escapeAttr(ogImage)}">
\t<link rel="preconnect" href="https://fonts.googleapis.com">
\t<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
\t<link href="https://fonts.googleapis.com/css2?family=Lavishly+Yours&amp;family=Victor+Mono:ital,wght@0,100..700;1,100..700&amp;display=swap" rel="stylesheet">
\t<link rel="stylesheet" href="https://danielestrella.com/css/fuentes.min.css">
\t<link rel="stylesheet" href="${cssHref}">
</head>
<body>
\t<header>
\t\t<p><a href="/">${logo} <strong>Daniel Estrella</strong></a></p>
\t\t<p>Sitio personal y reflexiones ✨</p>
\t\t<p class="estadisticas"><a href="/archivo/">🗄️ Archivo</a></p>
\t</header>
\t<main>
\t\t<article class="pagina entrada-${escapeAttr(path.posix.basename(group.slug))}">
\t\t\t<h1>${visibleTitle}</h1>
\t\t\t<p class="fecha-entrada"><time datetime="${local.iso}">${escapeHtml(local.fechaLegible)}</time></p>
\t\t\t<section class="texto-columna">
${content}
\t\t\t</section>
\t\t</article>
\t</main>
\t<footer class="pie-de-pagina">
\t\t<p>✨ DanielEstrella.com — DEstrella.mx ✨</p>
\t\t<p class="enlaces-al-pie"><a href="/">🦊 Inicio</a> <a href="/archivo/">🗄️ Archivo</a></p>
\t\t<p>🌸 Hecho con 💖 y mucho ☕️</p>
\t</footer>
\t<script src="${jsSrc}"></script>
</body>
</html>
`;
}

function writeEntry(group) {
  const dir = path.join(ROOT, group.slug);
  fs.mkdirSync(dir, { recursive: true });
  fs.writeFileSync(path.join(dir, "index.html"), renderEntry(group));
}

function mergeExisting(group) {
  const file = group.existing.file;
  let html = fs.readFileSync(file, "utf8");
  let changed = false;
  const validMedia = group.media.filter((item) => !item.missing);

  if (html.includes("/img/imagen-rota.png")) {
    const firstImage = validMedia.find((item) => item.kind === "image");
    if (firstImage) {
      html = html.replace(/src="\/img\/imagen-rota\.png"/, `src="${escapeAttr(firstImage.url)}"`);
      const absolute = `${DOMAIN}${firstImage.url}`;
      html = html.replace(/(<meta property="og:image" content=")[^"]*(")/, `$1${absolute}$2`);
      html = html.replace(/(<meta name="twitter:image" content=")[^"]*(")/, `$1${absolute}$2`);
      changed = true;
    }
  }

  let currentCount = countArticleMedia(html);
  const toAppend = validMedia.filter((item) => !html.includes(item.url)).slice(Math.max(0, currentCount));
  if (toAppend.length) {
    const block = `\n\t\t\t\t<div class="galeria-imagenes instagram-respaldo" data-lightbox-gallery>\n${toAppend.map((item, index) => item.kind === "image"
      ? figureImage(item, group.title, group.caption, currentCount + index, true)
      : figureVideo(item, group.title, currentCount + index, true)).join("\n")}\n\t\t\t\t</div>\n`;
    const sectionEnd = html.lastIndexOf("</section>");
    if (sectionEnd !== -1) {
      html = `${html.slice(0, sectionEnd)}${block}${html.slice(sectionEnd)}`;
      changed = true;
    }
  }

  if (changed) {
    fs.writeFileSync(file, html);
  }
  return changed;
}

function readAllEntries() {
  return walk("archivo")
    .filter((file) => /archivo\/\d{4}\/\d{2}\/[^/]+\/index\.html$/.test(file))
    .map(parseEntry)
    .filter(Boolean)
    .map((entry) => {
      const html = fs.readFileSync(entry.file, "utf8");
      const date = new Date(entry.timeIso);
      const local = localInfo(date);
      const metaImage = (html.match(/<meta property="og:image" content="([^"]+)"/) || [])[1] || "";
      let image = firstArticleImage(html) || metaImage.replace(DOMAIN, "") || "/portada.webp";
      if (image === `${DOMAIN}/portada.webp`) image = "/portada.webp";
      if (!image) image = "/portada.webp";
      return {
        ...entry,
        date,
        local,
        image,
        isVideo: articleHtml(html).includes("<video"),
        url: `/${entry.slug}/`,
      };
    })
    .sort((a, b) => b.date - a.date);
}

function archiveLink(page) {
  return page === 1 ? "/archivo/" : `/archivo/p/${page}/`;
}

function archivePaginationItems(page, totalPages) {
  const items = [];
  let previousVisible = 0;
  for (let num = 1; num <= totalPages; num += 1) {
    const visible = num === 1 || num === totalPages || Math.abs(num - page) <= 2;
    if (!visible) continue;
    if (previousVisible && num > previousVisible + 1) {
      items.push('\t\t\t\t\t<li><span class="paginacion-archivo__ellipsis" aria-hidden="true">...</span></li>');
    }
    if (num === page) {
      items.push(`\t\t\t\t\t<li><span class="actual" aria-current="page"><span class="oculto-visualmente">Página </span>${num}</span></li>`);
    } else {
      items.push(`\t\t\t\t\t<li><a href="${archiveLink(num)}"><span class="oculto-visualmente">Página </span>${num}</a></li>`);
    }
    previousVisible = num;
  }
  return items.join("\n");
}

function renderPreview(entry) {
  return `\t\t\t\t\t<article class="preview-entrada${entry.isVideo ? " preview-entrada--video" : ""}">
\t\t\t\t\t\t<a href="${escapeAttr(entry.url)}">
\t\t\t\t\t\t\t<img src="${escapeAttr(entry.image)}" alt="Miniatura de ${escapeAttr(entry.title)}" loading="lazy">
\t\t\t\t\t\t\t<strong>${escapeHtml(entry.title)}</strong>
\t\t\t\t\t\t\t<time datetime="${escapeAttr(entry.timeIso)}">${escapeHtml(entry.local.fechaArchivo)}</time>
\t\t\t\t\t\t</a>
\t\t\t\t\t</article>`;
}

function renderArchivePage(entries, page, totalPages) {
  const title = page === 1 ? "Archivo de Daniel Estrella" : `Archivo de Daniel Estrella - Página ${page}`;
  const h1 = page === 1 ? "Archivo de Daniel Estrella" : `Archivo de Daniel Estrella, página ${page}`;
  const description = `Archivo de entradas de Daniel Estrella sobre testing, tecnologia, cultura digital, proyectos personales y vida cotidiana.${page > 1 ? ` Pagina ${page} de ${totalPages}.` : ""}`;
  const canonical = `${DOMAIN}${archiveLink(page)}`;
  const prev = page > 1 ? `\t<link rel="prev" href="${DOMAIN}${archiveLink(page - 1)}">\n` : "";
  const next = page < totalPages ? `\t<link rel="next" href="${DOMAIN}${archiveLink(page + 1)}">\n` : "";

  const sections = [];
  for (const entry of entries) {
    const year = entry.local.year;
    let current = sections[sections.length - 1];
    if (!current || current.year !== year) {
      const firstSection = page === 1 && sections.length === 0;
      current = {
        year,
        heading: firstSection ? "Entradas recientes" : `Entradas de ${year}`,
        id: firstSection ? "titulo-archivo-entradas" : `titulo-archivo-${year}-${sections.length}`,
        entries: [],
      };
      sections.push(current);
    }
    current.entries.push(entry);
  }

  const sectionsHtml = sections.map((section) => `\t\t\t<section class="archivo-entradas" aria-labelledby="${section.id}">
\t\t\t\t<h2 id="${section.id}">${escapeHtml(section.heading)}</h2>
\t\t\t\t<div class="grid-masonry-entradas">
${section.entries.map(renderPreview).join("\n")}
\t\t\t\t</div>
\t\t\t</section>`).join("\n");

  const paginationItems = archivePaginationItems(page, totalPages);
  const pagination = `\t\t\t<nav class="paginacion-archivo" aria-label="Paginación del archivo">
\t\t\t\t<p>Página ${page} de ${totalPages}</p>
\t\t\t\t<div class="paginacion-archivo__saltos">
\t\t\t\t\t${page > 1 ? `<a class="paginacion-archivo__salto" href="${archiveLink(page - 1)}">Anterior</a>` : '<span class="paginacion-archivo__salto deshabilitado">Anterior</span>'}
\t\t\t\t\t${page < totalPages ? `<a class="paginacion-archivo__salto" href="${archiveLink(page + 1)}">Siguiente</a>` : '<span class="paginacion-archivo__salto deshabilitado">Siguiente</span>'}
\t\t\t\t</div>
\t\t\t\t<ol>
${paginationItems}
\t\t\t\t</ol>
\t\t\t</nav>`;

  return `<!DOCTYPE html>
<html lang="es">
<head>
\t<meta charset="UTF-8">
\t<meta name="viewport" content="width=device-width, initial-scale=1.0">
\t<title>${escapeHtml(title)}</title>
\t<meta name="description" content="${escapeAttr(description)}">
\t<meta property="og:title" content="${escapeAttr(title)}">
\t<meta property="og:description" content="${escapeAttr(description)}">
\t<meta property="og:type" content="website">
\t<meta property="og:url" content="${canonical}">
\t<meta property="og:image" content="${DOMAIN}/portada.webp">
\t<link rel="canonical" href="${canonical}">
${prev}${next}\t<link rel="preconnect" href="https://fonts.googleapis.com">
\t<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
\t<link href="https://fonts.googleapis.com/css2?family=Lavishly+Yours&amp;family=Victor+Mono:ital,wght@0,100..700;1,100..700&amp;display=swap" rel="stylesheet">
\t<link rel="stylesheet" href="https://danielestrella.com/css/fuentes.min.css">
\t<link rel="stylesheet" href="${cssHref}">
</head>
<body>
\t<header>
\t\t<p><a href="/">${logo} <strong>Daniel Estrella</strong></a></p>
\t\t<p>Sitio personal y reflexiones ✨</p>
\t\t<p class="estadisticas"><span class="actual">🗄️ Archivo</span></p>
\t</header>
\t<main>
\t\t<article class="indice archivo">
\t\t\t<h1>${escapeHtml(h1)}</h1>
${sectionsHtml}
${pagination}
\t\t</article>
\t</main>
\t<footer class="pie-de-pagina">
\t\t<p>✨ DanielEstrella.com — DEstrella.mx ✨</p>
\t\t<p class="enlaces-al-pie"><a href="/">🦊 Inicio</a></p>
\t\t<p>🌸 Hecho con 💖 y mucho ☕️</p>
\t</footer>
\t<script src="${jsSrc}"></script>
</body>
</html>
`;
}

function rebuildArchive(entries) {
  const perPage = 18;
  const totalPages = Math.ceil(entries.length / perPage);
  for (let page = 1; page <= totalPages; page += 1) {
    const pageEntries = entries.slice((page - 1) * perPage, page * perPage);
    const dir = page === 1 ? path.join(ROOT, "archivo") : path.join(ROOT, "archivo", "p", String(page));
    fs.mkdirSync(dir, { recursive: true });
    fs.writeFileSync(path.join(dir, "index.html"), renderArchivePage(pageEntries, page, totalPages));
  }
  return totalPages;
}

function xmlEscape(value) {
  return String(value).replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;");
}

function rebuildSitemap(entries, totalPages) {
  const today = `${new Date().toISOString().slice(0, 10)}T00:00:00-06:00`;
  const urls = [
    { loc: `${DOMAIN}/`, lastmod: "2021-03-29T18:52:28+00:00", priority: "1.00" },
  ];
  for (let page = 1; page <= totalPages; page += 1) {
    urls.push({
      loc: `${DOMAIN}${archiveLink(page)}`,
      lastmod: today,
      priority: page === 1 ? "0.80" : "0.70",
    });
  }
  for (const entry of entries) {
    urls.push({
      loc: `${DOMAIN}${entry.url}`,
      lastmod: entry.timeIso,
      priority: "0.80",
    });
  }
  const body = urls.map((url) => `<url>
  <loc>${xmlEscape(url.loc)}</loc>
  <lastmod>${xmlEscape(url.lastmod)}</lastmod>
  <priority>${url.priority}</priority>
</url>`).join("\n");
  fs.writeFileSync("sitemap.xml", `<?xml version="1.0" encoding="UTF-8"?>
<urlset
      xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
      xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
      xsi:schemaLocation="http://www.sitemaps.org/schemas/sitemap/0.9
            http://www.sitemaps.org/schemas/sitemap/0.9/sitemap.xsd">
${body}
</urlset>
`);
}

function csvEscape(value) {
  return `"${String(value ?? "").replace(/"/g, "\"\"")}"`;
}

function main() {
  const existingEntries = walk("archivo")
    .filter((file) => /archivo\/\d{4}\/\d{2}\/[^/]+\/index\.html$/.test(file))
    .map(parseEntry)
    .filter(Boolean);
  const existingByTime = new Map(existingEntries.map((entry) => [entry.timeKey, entry]));
  const usedSlugs = new Set(existingEntries.map((entry) => entry.slug));

  const { items, counts } = loadInstagramItems();
  const groups = groupItems(items);
  const rows = [["nombre de entrada", "archivo local", "archivo remoto"]];
  const stats = {
    zipCounts: counts,
    mediaProcesados: items.length,
    gruposInstagram: groups.length,
    entradasExistentesCombinadas: 0,
    entradasNuevas: 0,
    mediosCopiados: 0,
    mediosFaltantes: 0,
  };

  for (const group of groups) {
    group.local = localInfo(new Date(group.firstTime));
    group.existing = existingByTime.get(group.local.key) || null;
    group.title = group.existing ? group.existing.title : titleFromCaption(group.caption, group.local);
    group.summary = summaryFromCaption(group.caption, group.local, group.location);
    group.slug = group.existing ? group.existing.slug : uniqueSlug(group.title, group.local, usedSlugs);
    group.media = [];

    for (const [index, item] of group.items.entries()) {
      try {
        const copied = copyMedia(item, group.slug, index);
        group.media.push(copied);
        stats.mediosCopiados += 1;
        rows.push([group.title, copied.rel, ""]);
      } catch (error) {
        stats.mediosFaltantes += 1;
        group.media.push({
          source: item.path,
          rel: item.path,
          url: "/img/imagen-rota.png",
          kind: isImage(item) ? "image" : "video",
          mime: mimeFor(item.path),
          missing: true,
        });
      }
    }

    if (group.existing) {
      if (mergeExisting(group)) {
        stats.entradasExistentesCombinadas += 1;
      }
    } else {
      writeEntry(group);
      stats.entradasNuevas += 1;
    }
  }

  fs.writeFileSync(path.join(ROOT, CSV_REL), rows.map((row) => row.map(csvEscape).join(",")).join("\n") + "\n");

  const entries = readAllEntries();
  const totalArchivePages = rebuildArchive(entries);
  rebuildSitemap(entries, totalArchivePages);
  stats.totalEntradasArchivo = entries.length;
  stats.paginasArchivo = totalArchivePages;
  stats.csv = CSV_REL;
  console.log(JSON.stringify(stats, null, 2));
}

main();
