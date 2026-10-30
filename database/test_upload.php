<!DOCTYPE html>
<html lang="id">
<head>
  <meta charset="UTF-8">
  <title>Test Upload Foto – TahuKocek</title>
  <style>
    body { font-family: sans-serif; padding: 24px; max-width: 500px; }
    input, button { margin: 8px 0; display: block; }
    pre { background: #f5f5f5; padding: 12px; border-radius: 8px; white-space: pre-wrap; }
    img { max-width: 100%; border-radius: 8px; margin-top: 12px; }
  </style>
</head>
<body>
<h2>🧪 Test Upload Foto Varian</h2>
<p style="color:#888">File ini hanya untuk debugging. Hapus setelah selesai.</p>

<label>ID Varian (opsional, cth: 1):</label>
<input type="number" id="varianId" value="1" />

<label>Pilih Gambar:</label>
<input type="file" id="fotoFile" accept="image/*" />

<button onclick="testUpload()">Upload</button>

<div id="result"></div>

<script>
async function testUpload() {
  const file = document.getElementById('fotoFile').files[0];
  const vid  = document.getElementById('varianId').value;
  const out  = document.getElementById('result');

  if (!file) { out.innerHTML = '<p style="color:red">Pilih file dulu!</p>'; return; }

  out.innerHTML = '<p>⏳ Mengupload...</p>';

  const fd = new FormData();
  fd.append('foto', file);
  if (vid) fd.append('varian_id', vid);

  try {
    const res  = await fetch('../resources/api/upload.php', { method: 'POST', body: fd });
    const text = await res.text();
    let json;
    try { json = JSON.parse(text); } catch(e) { json = { raw: text }; }

    if (json.success) {
      out.innerHTML = `
        <p style="color:green">✓ Upload berhasil!</p>
        <pre>${JSON.stringify(json, null, 2)}</pre>
        <img src="${json.url}" alt="hasil upload">
      `;
    } else {
      out.innerHTML = `
        <p style="color:red">✗ Gagal: ${json.message || 'Unknown error'}</p>
        <pre>${JSON.stringify(json, null, 2)}</pre>
        <details><summary>Raw response</summary><pre>${text}</pre></details>
      `;
    }
  } catch(e) {
    out.innerHTML = `<p style="color:red">✗ Network error: ${e.message}</p>`;
  }
}
</script>
</body>
</html>
