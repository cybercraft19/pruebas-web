const Api = (() => {
  function getCookie(name) {
    const match = document.cookie.match(new RegExp('(^| )' + name + '=([^;]+)'));
    return match ? decodeURIComponent(match[2]) : null;
  }

  async function ensureCsrfCookie() {
    if (!getCookie('XSRF-TOKEN')) {
      await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    }
  }

  async function request(url, options = {}) {
    await ensureCsrfCookie();

    const headers = {
      Accept: 'application/json',
      'X-XSRF-TOKEN': getCookie('XSRF-TOKEN'),
      ...(options.headers || {}),
    };

    if (options.body && !(options.body instanceof FormData)) {
      headers['Content-Type'] = 'application/json';
    }

    const response = await fetch(url, {
      credentials: 'include',
      ...options,
      headers,
    });

    if (response.status === 204) {
      return null;
    }

    const contentType = response.headers.get('content-type') || '';
    const payload = contentType.includes('application/json') ? await response.json() : null;

    if (!response.ok) {
      const error = new Error((payload && payload.message) || `Error ${response.status}`);
      error.status = response.status;
      error.payload = payload;
      throw error;
    }

    return payload;
  }

  return {
    get: (url) => request(url),
    post: (url, body) => request(url, { method: 'POST', body: body instanceof FormData ? body : JSON.stringify(body || {}) }),
    postForm: (url, formData) => request(url, { method: 'POST', body: formData }),
    download: async (url, filename) => {
      await ensureCsrfCookie();
      const response = await fetch(url, { credentials: 'include', headers: { 'X-XSRF-TOKEN': getCookie('XSRF-TOKEN') } });
      const blob = await response.blob();
      const link = document.createElement('a');
      link.href = URL.createObjectURL(blob);
      link.download = filename;
      link.click();
      URL.revokeObjectURL(link.href);
    },
  };
})();

async function requireSession(expectedRole) {
  try {
    const data = await Api.get('/api/user');
    const user = data.user;
    if (!user) throw new Error('no-user');
    if (expectedRole && user.role !== expectedRole) {
      window.location.href = user.role === 'evaluador' ? '/app/evaluador/index.html' : '/app/estudiante/index.html';
      return null;
    }
    return user;
  } catch (e) {
    window.location.href = '/app/login.html';
    return null;
  }
}

function formatError(error) {
  if (error.payload && error.payload.errors) {
    return Object.values(error.payload.errors).flat().join('\n');
  }
  return error.message;
}
