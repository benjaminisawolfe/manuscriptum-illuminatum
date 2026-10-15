// Generated, non-personal documents. No binaries or uploaded fixtures are committed.
function pdf(text = 'Disposable Character document fixture') {
    const stream = `BT /F1 12 Tf 40 140 Td (${text.replace(/[()\\]/g, '')}) Tj ET`;
    const objects = [
        '<< /Type /Catalog /Pages 2 0 R >>',
        '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
        '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 300 200] /Resources << /Font << /F1 4 0 R >> >> /Contents 5 0 R >>',
        '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        `<< /Length ${Buffer.byteLength(stream)} >>\nstream\n${stream}\nendstream`,
    ];
    let content = '%PDF-1.4\n';
    const offsets = [0];
    for (const [index, object] of objects.entries()) {
        offsets.push(Buffer.byteLength(content));
        content += `${index + 1} 0 obj\n${object}\nendobj\n`;
    }
    const start = Buffer.byteLength(content);
    content += `xref\n0 ${offsets.length}\n0000000000 65535 f \n`;
    content += offsets.slice(1).map(offset => `${String(offset).padStart(10, '0')} 00000 n \n`).join('');
    return Buffer.from(`${content}trailer\n<< /Size ${offsets.length} /Root 1 0 R >>\nstartxref\n${start}\n%%EOF\n`);
}

function crc32(bytes) {
    let result = 0xffffffff;
    for (const byte of bytes) {
        result ^= byte;
        for (let bit = 0; bit < 8; bit++) result = (result >>> 1) ^ (result & 1 ? 0xedb88320 : 0);
    }
    return (result ^ 0xffffffff) >>> 0;
}

// Stored ZIP members keep ODT's required first, uncompressed mimetype member intact.
function zip(entries) {
    const locals = [], central = [];
    let offset = 0;
    for (const [filename, text] of entries) {
        const name = Buffer.from(filename), data = Buffer.from(text), checksum = crc32(data);
        const local = Buffer.alloc(30), directory = Buffer.alloc(46);
        local.writeUInt32LE(0x04034b50); local.writeUInt16LE(20, 4);
        local.writeUInt32LE(checksum, 14); local.writeUInt32LE(data.length, 18);
        local.writeUInt32LE(data.length, 22); local.writeUInt16LE(name.length, 26);
        directory.writeUInt32LE(0x02014b50); directory.writeUInt16LE(20, 4); directory.writeUInt16LE(20, 6);
        directory.writeUInt32LE(checksum, 16); directory.writeUInt32LE(data.length, 20);
        directory.writeUInt32LE(data.length, 24); directory.writeUInt16LE(name.length, 28);
        directory.writeUInt32LE(offset, 42);
        locals.push(local, name, data); central.push(directory, name);
        offset += local.length + name.length + data.length;
    }
    const end = Buffer.alloc(22), table = Buffer.concat(central);
    end.writeUInt32LE(0x06054b50); end.writeUInt16LE(entries.length, 8); end.writeUInt16LE(entries.length, 10);
    end.writeUInt32LE(table.length, 12); end.writeUInt32LE(offset, 16);
    return Buffer.concat([...locals, table, end]);
}

function documentFiles(suffix = 'fixture') {
    const file = (extension, mimeType, buffer) => ({ name: `fixture-document-${suffix}.${extension}`, mimeType, buffer: Buffer.from(buffer) });
    const docx = zip([
        ['[Content_Types].xml', '<?xml version="1.0"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types"><Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/><Override PartName="/word/document.xml" ContentType="application/vnd.openxmlformats-officedocument.wordprocessingml.document.main+xml"/></Types>'],
        ['_rels/.rels', '<?xml version="1.0"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships"><Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="word/document.xml"/></Relationships>'],
        ['word/document.xml', '<?xml version="1.0"?><w:document xmlns:w="http://schemas.openxmlformats.org/wordprocessingml/2006/main"><w:body><w:p><w:r><w:t>Disposable Character document fixture</w:t></w:r></w:p></w:body></w:document>'],
    ]);
    const odt = zip([
        ['mimetype', 'application/vnd.oasis.opendocument.text'],
        ['content.xml', '<?xml version="1.0"?><office:document-content xmlns:office="urn:oasis:names:tc:opendocument:xmlns:office:1.0" xmlns:text="urn:oasis:names:tc:opendocument:xmlns:text:1.0" office:version="1.2"><office:body><office:text><text:p>Disposable Character document fixture</text:p></office:text></office:body></office:document-content>'],
        ['META-INF/manifest.xml', '<?xml version="1.0"?><manifest:manifest xmlns:manifest="urn:oasis:names:tc:opendocument:xmlns:manifest:1.0" manifest:version="1.2"><manifest:file-entry manifest:full-path="/" manifest:media-type="application/vnd.oasis.opendocument.text"/><manifest:file-entry manifest:full-path="content.xml" manifest:media-type="text/xml"/></manifest:manifest>'],
    ]);
    return {
        pdf: file('pdf', 'application/pdf', pdf()),
        txt: file('txt', 'text/plain', 'Disposable Character document fixture. No private campaign information.\n'),
        rtf: file('rtf', 'application/rtf', '{\\rtf1\\ansi\\deff0 {\\fonttbl {\\f0 Helvetica;}}\\f0 Disposable Character document fixture.\\par}'),
        docx: file('docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', docx),
        odt: file('odt', 'application/vnd.oasis.opendocument.text', odt),
        png: file('png', 'image/png', Buffer.from('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN2kAAAAASUVORK5CYII=', 'base64')),
        zip: file('zip', 'application/zip', zip([['fixture.txt', 'Harmless archive fixture.']])),
        exe: file('exe', 'application/octet-stream', 'MZ harmless non-executable fixture bytes'),
        html: file('html', 'text/html', '<!doctype html><html><body>Harmless fixture</body></html>'),
        svg: file('svg', 'image/svg+xml', '<svg xmlns="http://www.w3.org/2000/svg"><title>Harmless fixture</title></svg>'),
    };
}

module.exports = { documentFiles };
