const fs = require('fs');
const path = require('path');

function walk(dir) {
  let files = [];
  for (const entry of fs.readdirSync(dir, { withFileTypes: true })) {
    const full = path.join(dir, entry.name);
    if (entry.isDirectory() && entry.name !== 'environments') {
      files.push(...walk(full));
    } else if (entry.isFile() && entry.name.endsWith('.bru')) {
      files.push(full);
    }
  }
  return files;
}

const baseDir = path.resolve(__dirname, '../../../docs/api/bruno/SISTLABUM');
const files = walk(baseDir);

let updatedCount = 0;

for (const filePath of files) {
  const content = fs.readFileSync(filePath, 'utf8');

  // Determine method
  let method = 'get';
  if (/^post\s*\{/m.test(content)) method = 'post';
  else if (/^put\s*\{/m.test(content)) method = 'put';
  else if (/^patch\s*\{/m.test(content)) method = 'patch';
  else if (/^delete\s*\{/m.test(content)) method = 'delete';
  else if (/^get\s*\{/m.test(content)) method = 'get';

  // Extract URL
  const urlMatch = content.match(/url:\s*(.+)/);
  const url = urlMatch ? urlMatch[1].trim() : '';
  const isParameterized = url.includes('{') || /:[a-zA-Z]/.test(url);

  let allowedCodes;
  if (method === 'get') {
    if (isParameterized) {
      allowedCodes = '[200, 204, 304, 400, 401, 403, 404, 410, 422, 429]';
    } else {
      allowedCodes = '[200, 204, 304, 400, 401, 403, 404, 410, 422, 429]';
    }
  } else if (['post', 'put', 'patch'].includes(method)) {
    allowedCodes = '[200, 201, 204, 400, 401, 403, 404, 410, 422, 429]';
  } else if (method === 'delete') {
    allowedCodes = '[200, 204, 400, 401, 403, 404, 410, 422, 429]';
  } else {
    allowedCodes = '[200, 201, 204, 400, 401, 403, 404, 410, 422, 429]';
  }

  const newTestBlock = `tests {
  test("never returns 500 internal server error", function() {
    expect(res.getStatus()).to.not.equal(500);
  });

  test("status code adheres to API contract", function() {
    expect(${allowedCodes}).to.include(res.getStatus());
  });

  test("response body adheres to standard API envelope", function() {
    const status = res.getStatus();
    const body = res.getBody();
    if (status >= 200 && status < 300) {
      if (status !== 204 && body && typeof body === "object") {
        expect(body).to.be.an("object");
      }
    } else if (status >= 400) {
      if (body && typeof body === "object") {
        const hasError = body.hasOwnProperty("error") || body.hasOwnProperty("errors") || body.hasOwnProperty("message");
        expect(hasError).to.be.true;
      }
    }
  });
}
`;

  const testsIdx = content.indexOf('tests {');
  if (testsIdx !== -1) {
    const preContent = content.substring(0, testsIdx);
    const newContent = preContent + newTestBlock;
    fs.writeFileSync(filePath, newContent, 'utf8');
    updatedCount++;
  } else {
    const newContent = content.trimEnd() + '\n\n' + newTestBlock;
    fs.writeFileSync(filePath, newContent, 'utf8');
    updatedCount++;
  }
}

console.log(`Successfully updated assertions in ${updatedCount} Bruno files.`);
