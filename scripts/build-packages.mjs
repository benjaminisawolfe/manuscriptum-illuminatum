import { execFile } from 'node:child_process';
import { promisify } from 'node:util';
import { deflateRawSync, inflateRawSync } from 'node:zlib';
import { mkdir, readdir, readFile, rm, stat, writeFile } from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { fileURLToPath } from 'node:url';

const repositoryRoot = path.resolve(path.dirname(fileURLToPath(import.meta.url)), '..');
const packagesDirectory = path.join(repositoryRoot, 'packages');
const validateOnly = process.argv.includes('--validate-only');
const requireTracked = process.argv.includes('--require-tracked');
const fixedDosDate = 0x0021;
const fixedDosTime = 0;
const execFileAsync = promisify(execFile);

const packages = [
    {
        label: 'theme',
        source: path.join(repositoryRoot, 'themes', 'paginae-manuscripti-illuminati'),
        root: 'paginae-manuscripti-illuminati',
        archive: path.join(packagesDirectory, 'paginae-manuscripti-illuminati.zip'),
        required: ['paginae-manuscripti-illuminati/style.css', 'paginae-manuscripti-illuminati/functions.php'],
        versionFile: 'paginae-manuscripti-illuminati/style.css',
    },
    {
        label: 'plugin',
        source: path.join(repositoryRoot, 'plugins', 'ligatura-manuscripti-illuminati'),
        root: 'ligatura-manuscripti-illuminati',
        archive: path.join(packagesDirectory, 'ligatura-manuscripti-illuminati.zip'),
        required: ['ligatura-manuscripti-illuminati/ligatura-manuscripti-illuminati.php'],
        versionFile: 'ligatura-manuscripti-illuminati/ligatura-manuscripti-illuminati.php',
    },
];

const excludedSegments = new Set([
    '.git', '.github', '.auth', 'node_modules', 'tests', 'test-results',
    'playwright', 'playwright-report', 'coverage', '.cache', '.pytest_cache',
    '.idea', '.vscode', 'blob-report',
]);

const excludedBasenames = new Set([
    '.ds_store', 'agents.md', 'thumbs.db', 'wp-config.php',
]);

const sensitiveContentPatterns = [
    { label: 'private key', pattern: /-----BEGIN (?:RSA |EC |OPENSSH )?PRIVATE KEY-----/ },
    { label: 'Playwright authentication state', pattern: /playwright[\\/]\.auth/i },
    { label: 'hard-coded password', pattern: /password\s*[:=]\s*['"][^'"]+['"]/i },
    { label: 'hard-coded API key', pattern: /api[_-]?key\s*[:=]\s*['"][^'"]+['"]/i },
];

function isExcluded(relativePath) {
    const segments = relativePath.split(/[\\/]+/);
    const basename = segments.at(-1) || '';
    const lowerBasename = basename.toLowerCase();

    return segments.some((segment) => excludedSegments.has(segment.toLowerCase()))
        || excludedBasenames.has(lowerBasename)
        || lowerBasename === '.env'
        || lowerBasename.startsWith('.env.')
        || /^(?:task|handoff)_.*\.md$/i.test(basename)
        || /\.(?:bak|key|log|pem|sw[op]|tmp)$/i.test(basename)
        || basename.endsWith('~');
}

async function collectFiles(directory, relativeDirectory = '') {
    const entries = await readdir(directory, { withFileTypes: true });
    const files = [];

    for (const entry of entries.sort((left, right) => left.name.localeCompare(right.name))) {
        const relativePath = path.join(relativeDirectory, entry.name);

        if (isExcluded(relativePath)) {
            continue;
        }

        const absolutePath = path.join(directory, entry.name);

        if (entry.isDirectory()) {
            files.push(...await collectFiles(absolutePath, relativePath));
        } else if (entry.isFile()) {
            files.push({
                absolutePath,
                archivePath: relativePath.split(path.sep).join('/'),
            });
        }
    }

    return files;
}

const crcTable = new Uint32Array(256);
for (let index = 0; index < 256; index += 1) {
    let value = index;
    for (let bit = 0; bit < 8; bit += 1) {
        value = (value & 1) ? (0xedb88320 ^ (value >>> 1)) : (value >>> 1);
    }
    crcTable[index] = value >>> 0;
}

function crc32(buffer) {
    let value = 0xffffffff;
    for (const byte of buffer) {
        value = crcTable[(value ^ byte) & 0xff] ^ (value >>> 8);
    }
    return (value ^ 0xffffffff) >>> 0;
}

function createZip(entries) {
    const localParts = [];
    const centralParts = [];
    let offset = 0;

    for (const entry of entries) {
        const name = Buffer.from(entry.name, 'utf8');
        const compressed = deflateRawSync(entry.content, { level: 9 });
        const checksum = crc32(entry.content);
        const localHeader = Buffer.alloc(30);

        localHeader.writeUInt32LE(0x04034b50, 0);
        localHeader.writeUInt16LE(20, 4);
        localHeader.writeUInt16LE(0x0800, 6);
        localHeader.writeUInt16LE(8, 8);
        localHeader.writeUInt16LE(fixedDosTime, 10);
        localHeader.writeUInt16LE(fixedDosDate, 12);
        localHeader.writeUInt32LE(checksum, 14);
        localHeader.writeUInt32LE(compressed.length, 18);
        localHeader.writeUInt32LE(entry.content.length, 22);
        localHeader.writeUInt16LE(name.length, 26);
        localHeader.writeUInt16LE(0, 28);

        localParts.push(localHeader, name, compressed);

        const centralHeader = Buffer.alloc(46);
        centralHeader.writeUInt32LE(0x02014b50, 0);
        centralHeader.writeUInt16LE(20, 4);
        centralHeader.writeUInt16LE(20, 6);
        centralHeader.writeUInt16LE(0x0800, 8);
        centralHeader.writeUInt16LE(8, 10);
        centralHeader.writeUInt16LE(fixedDosTime, 12);
        centralHeader.writeUInt16LE(fixedDosDate, 14);
        centralHeader.writeUInt32LE(checksum, 16);
        centralHeader.writeUInt32LE(compressed.length, 20);
        centralHeader.writeUInt32LE(entry.content.length, 24);
        centralHeader.writeUInt16LE(name.length, 28);
        centralHeader.writeUInt16LE(0, 30);
        centralHeader.writeUInt16LE(0, 32);
        centralHeader.writeUInt16LE(0, 34);
        centralHeader.writeUInt16LE(0, 36);
        centralHeader.writeUInt32LE(0, 38);
        centralHeader.writeUInt32LE(offset, 42);
        centralParts.push(centralHeader, name);

        offset += localHeader.length + name.length + compressed.length;
    }

    const centralDirectory = Buffer.concat(centralParts);
    const end = Buffer.alloc(22);
    end.writeUInt32LE(0x06054b50, 0);
    end.writeUInt16LE(0, 4);
    end.writeUInt16LE(0, 6);
    end.writeUInt16LE(entries.length, 8);
    end.writeUInt16LE(entries.length, 10);
    end.writeUInt32LE(centralDirectory.length, 12);
    end.writeUInt32LE(offset, 16);
    end.writeUInt16LE(0, 20);

    return Buffer.concat([...localParts, centralDirectory, end]);
}

function readZipEntries(buffer) {
    const endSignature = Buffer.from([0x50, 0x4b, 0x05, 0x06]);
    const endOffset = buffer.lastIndexOf(endSignature);

    if (endOffset < 0 || endOffset + 22 > buffer.length) {
        throw new Error('ZIP end-of-central-directory record is missing.');
    }

    const count = buffer.readUInt16LE(endOffset + 10);
    let offset = buffer.readUInt32LE(endOffset + 16);
    const entries = [];

    for (let index = 0; index < count; index += 1) {
        if (offset + 46 > buffer.length) {
            throw new Error('ZIP central directory entry is truncated.');
        }

        if (buffer.readUInt32LE(offset) !== 0x02014b50) {
            throw new Error('ZIP central directory is malformed.');
        }

        const compressionMethod = buffer.readUInt16LE(offset + 10);
        const checksum = buffer.readUInt32LE(offset + 16);
        const compressedSize = buffer.readUInt32LE(offset + 20);
        const uncompressedSize = buffer.readUInt32LE(offset + 24);
        const nameLength = buffer.readUInt16LE(offset + 28);
        const extraLength = buffer.readUInt16LE(offset + 30);
        const commentLength = buffer.readUInt16LE(offset + 32);
        const localOffset = buffer.readUInt32LE(offset + 42);
        const name = buffer.subarray(offset + 46, offset + 46 + nameLength).toString('utf8');

        if (name.startsWith('/') || name.split('/').includes('..')) {
            throw new Error(`ZIP contains an unsafe path: ${name}`);
        }

        if (compressionMethod !== 8) {
            throw new Error(`ZIP entry uses an unsupported compression method: ${name}`);
        }

        if (localOffset + 30 > buffer.length || buffer.readUInt32LE(localOffset) !== 0x04034b50) {
            throw new Error(`ZIP local header is missing or malformed: ${name}`);
        }

        const localNameLength = buffer.readUInt16LE(localOffset + 26);
        const localExtraLength = buffer.readUInt16LE(localOffset + 28);
        const localName = buffer.subarray(localOffset + 30, localOffset + 30 + localNameLength).toString('utf8');
        const dataOffset = localOffset + 30 + localNameLength + localExtraLength;
        const dataEnd = dataOffset + compressedSize;

        if (localName !== name) {
            throw new Error(`ZIP local and central filenames disagree: ${name}`);
        }

        if (dataEnd > buffer.length) {
            throw new Error(`ZIP entry data is truncated: ${name}`);
        }

        const content = inflateRawSync(buffer.subarray(dataOffset, dataEnd));
        if (content.length !== uncompressedSize || crc32(content) !== checksum) {
            throw new Error(`ZIP entry failed size or CRC validation: ${name}`);
        }

        entries.push({ name, content });
        offset += 46 + nameLength + extraLength + commentLength;
    }

    return entries;
}

async function validatePackage(definition) {
    const archiveStats = await stat(definition.archive);

    if (!archiveStats.isFile() || archiveStats.size === 0) {
        throw new Error(`${definition.label} package is missing or empty.`);
    }

    const entries = readZipEntries(await readFile(definition.archive));
    const names = entries.map((entry) => entry.name);
    const roots = new Set(names.map((entry) => entry.split('/')[0]));

    if (roots.size !== 1 || !roots.has(definition.root)) {
        throw new Error(`${definition.label} ZIP must contain exactly the ${definition.root} top-level directory.`);
    }

    for (const required of definition.required) {
        if (!names.includes(required)) {
            throw new Error(`${definition.label} ZIP is missing ${required}.`);
        }
    }

    const forbidden = names.find((entry) => isExcluded(entry) || entry.includes('/packages/'));
    if (forbidden) {
        throw new Error(`${definition.label} ZIP contains excluded development material: ${forbidden}`);
    }

    for (const entry of entries) {
        const content = entry.content.toString('utf8');
        const sensitive = sensitiveContentPatterns.find(({ pattern }) => pattern.test(content));
        if (sensitive) {
            throw new Error(`${definition.label} ZIP contains a potential ${sensitive.label}: ${entry.name}`);
        }
    }

    const versionEntry = entries.find((entry) => entry.name === definition.versionFile);
    const versionMatch = versionEntry?.content.toString('utf8').match(/^\s*(?:\*\s*)?Version:\s*([0-9]+(?:\.[0-9]+)*)\s*$/m);

    if (!versionMatch) {
        throw new Error(`${definition.label} ZIP does not declare a readable Version header in ${definition.versionFile}.`);
    }

    const version = versionMatch[1];

    process.stdout.write(`\n${definition.label.toUpperCase()} PACKAGE: ${path.relative(repositoryRoot, definition.archive)} (${archiveStats.size} bytes)\n`);
    process.stdout.write(`  root: ${definition.root}/\n`);
    process.stdout.write(`  version: ${version}\n`);
    process.stdout.write(`  files: ${names.length}\n`);
    process.stdout.write(`  required: ${definition.required.join(', ')}\n`);

    return version;
}

async function validateTrackedPackages() {
    const relativeArchives = packages.map((definition) => path.relative(repositoryRoot, definition.archive).split(path.sep).join('/'));

    try {
        const { stdout } = await execFileAsync(
            'git',
            ['ls-files', '--error-unmatch', '--', ...relativeArchives],
            { cwd: repositoryRoot },
        );
        const tracked = new Set(stdout.trim().split(/\r?\n/).filter(Boolean));
        const missing = relativeArchives.find((archive) => !tracked.has(archive));

        if (missing) {
            throw new Error(`${missing} is not tracked by Git.`);
        }
    } catch (error) {
        throw new Error(`Canonical package ZIPs must be tracked by Git: ${error.message}`);
    }

    process.stdout.write('\nGit tracking validation passed for both canonical package ZIPs.\n');
}

if (!validateOnly) {
    await mkdir(packagesDirectory, { recursive: true });

    for (const entry of await readdir(packagesDirectory, { withFileTypes: true })) {
        if (entry.isFile() && entry.name.toLowerCase().endsWith('.zip')) {
            await rm(path.join(packagesDirectory, entry.name));
        }
    }

    for (const definition of packages) {
        for (const required of definition.required) {
            const sourceRelative = required.slice(definition.root.length + 1);
            const sourceRequired = path.join(definition.source, sourceRelative.replaceAll('/', path.sep));
            const requiredStats = await stat(sourceRequired).catch(() => null);
            if (!requiredStats?.isFile()) {
                throw new Error(`Required ${definition.label} file is missing: ${required}`);
            }
        }

        const files = await collectFiles(definition.source);
        const entries = [];

        for (const file of files) {
            entries.push({
                name: `${definition.root}/${file.archivePath}`,
                content: await readFile(file.absolutePath),
            });
        }

        if (entries.length === 0) {
            throw new Error(`No runtime files were found for the ${definition.label} package.`);
        }

        await writeFile(definition.archive, createZip(entries));
    }
}

const versions = new Set();
for (const definition of packages) {
    versions.add(await validatePackage(definition));
}

if (versions.size !== 1) {
    throw new Error(`Theme and plugin package versions must match; found ${[...versions].join(', ')}.`);
}

if (requireTracked) {
    await validateTrackedPackages();
}

process.stdout.write('\nPackage validation passed.\n');
for (const definition of packages) {
    process.stdout.write(`${path.resolve(definition.archive)}\n`);
}
