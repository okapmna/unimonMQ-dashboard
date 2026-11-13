const FERTILITY_API_URL = (process.env.FERTILITY_API_URL || '').replace(/\/+$/, '');
const FERTILITY_TIMEOUT_MS = parseInt(process.env.FERTILITY_TIMEOUT_MS || '120000', 10);

function isEnabled() {
  return Boolean(FERTILITY_API_URL);
}

async function analyzeImage(buffer, filename = 'capture.jpg', options = {}) {
  if (!FERTILITY_API_URL) {
    throw new Error('FERTILITY_API_URL belum diset');
  }

  const form = new FormData();
  form.append('images', new Blob([buffer], { type: 'image/jpeg' }), filename);

  if (options.detConf !== undefined) form.append('det_conf', String(options.detConf));
  if (options.thrFertile !== undefined) form.append('thr_fertile', String(options.thrFertile));
  if (options.cropMargin !== undefined) form.append('crop_margin', String(options.cropMargin));
  if (options.classifierFile) form.append('classifier_file', options.classifierFile);

  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), FERTILITY_TIMEOUT_MS);

  try {
    const res = await fetch(`${FERTILITY_API_URL}/api/predict`, {
      method: 'POST',
      body: form,
      signal: controller.signal,
    });

    const text = await res.text();
    let data = null;
    try {
      data = JSON.parse(text);
    } catch (e) {
      data = null;
    }

    if (!res.ok || !data || data.ok === false) {
      const message = (data && data.error) || `Fertility API HTTP ${res.status}`;
      throw new Error(message);
    }

    return data;
  } finally {
    clearTimeout(timer);
  }
}

function dataUrlToBuffer(dataUrl) {
  const match = /^data:image\/[\w.+-]+;base64,(.+)$/.exec(dataUrl || '');
  return match ? Buffer.from(match[1], 'base64') : null;
}

module.exports = {
  FERTILITY_API_URL,
  isEnabled,
  analyzeImage,
  dataUrlToBuffer,
};
