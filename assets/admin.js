/* PWA Manager - interaksi panel admin */
(function () {
  'use strict';

  /* ------------------------------------------------------------- Toast */

  var toastEl = null;
  var toastTimer = null;

  function toast(msg, isError) {
    if (!toastEl) {
      toastEl = document.createElement('div');
      toastEl.className = 'toast';
      document.body.appendChild(toastEl);
    }
    toastEl.textContent = msg;
    toastEl.classList.toggle('is-error', !!isError);
    // paksa reflow supaya transisi jalan saat toast dipanggil beruntun
    void toastEl.offsetWidth;
    toastEl.classList.add('is-visible');
    clearTimeout(toastTimer);
    toastTimer = setTimeout(function () {
      toastEl.classList.remove('is-visible');
    }, 2600);
  }

  function postForm(url, data) {
    var body = new URLSearchParams(data);
    body.set('_token', window.CSRF_TOKEN);
    return fetch(url, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
        'X-Requested-With': 'XMLHttpRequest',
        'X-CSRF-Token': window.CSRF_TOKEN
      },
      body: body.toString(),
      credentials: 'same-origin'
    }).then(function (res) {
      return res.json().catch(function () {
        return { ok: false, error: 'Respons server tidak valid (HTTP ' + res.status + ').' };
      });
    });
  }

  /* ------------------------------------- Ubah target link langsung dari tabel */

  document.querySelectorAll('.quick-target').forEach(function (form) {
    var input = form.querySelector('input[name="target_url"]');
    var btn = form.querySelector('button');
    var original = input.value;

    input.addEventListener('input', function () {
      form.classList.toggle('is-dirty', input.value !== original);
      form.classList.remove('is-saved');
    });

    form.addEventListener('submit', function (ev) {
      ev.preventDefault();
      if (input.value === original) {
        toast('Target link belum berubah.');
        return;
      }
      btn.disabled = true;
      btn.textContent = 'Menyimpan…';

      postForm(window.BASE + 'admin/quick-target', { id: form.dataset.id, target_url: input.value })
        .then(function (res) {
          if (res.ok) {
            original = res.target_url;
            input.value = res.target_url;
            form.classList.remove('is-dirty');
            form.classList.add('is-saved');
            var note = form.parentElement.querySelector('.target-note');
            if (note) note.textContent = 'Diubah ' + res.updated_at;
            toast('Target link tersimpan.');
          } else {
            toast(res.error || 'Gagal menyimpan.', true);
          }
        })
        .catch(function () { toast('Koneksi ke server gagal.', true); })
        .finally(function () {
          btn.disabled = false;
          btn.textContent = 'Simpan';
        });
    });
  });

  /* --------------------------------------------------- Aktif / nonaktif PWA */

  document.querySelectorAll('.toggle').forEach(function (btn) {
    btn.addEventListener('click', function () {
      btn.disabled = true;
      postForm(window.BASE + 'admin/toggle', { id: btn.dataset.id })
        .then(function (res) {
          if (res.ok) {
            btn.classList.toggle('is-on', res.active);
            btn.querySelector('.toggle-text').textContent = res.active ? 'Aktif' : 'Nonaktif';
            toast(res.active ? 'PWA diaktifkan.' : 'PWA dinonaktifkan.');
          } else {
            toast(res.error || 'Gagal mengubah status.', true);
          }
        })
        .catch(function () { toast('Koneksi ke server gagal.', true); })
        .finally(function () { btn.disabled = false; });
    });
  });

  /* ------------------------------------------------------ Salin link install */

  document.querySelectorAll('.copy-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var text = btn.dataset.copy;
      var done = function () {
        toast('Link install disalin.');
        // Tombolnya kini berupa ikon, jadi umpan baliknya lewat warna
        btn.classList.add('is-done');
        setTimeout(function () { btn.classList.remove('is-done'); }, 1400);
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, fallback);
      } else {
        fallback();
      }

      function fallback() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); }
        catch (e) { toast('Gagal menyalin, salin manual: ' + text, true); }
        document.body.removeChild(ta);
      }
    });
  });

  /* ---------------------------------------- Salin isi blok kode (halaman embed) */

  document.querySelectorAll('.copy-code').forEach(function (btn) {
    btn.addEventListener('click', function () {
      var panel = btn.closest('.file-panel');
      var code = panel && panel.querySelector('pre.code code');
      if (!code) return;

      var text = code.textContent;
      var name = panel.querySelector('.file-name');
      var label = name ? name.textContent : 'Kode';

      var done = function () {
        toast(label + ' disalin.');
        btn.textContent = 'Tersalin';
        setTimeout(function () { btn.textContent = 'Salin'; }, 1800);
      };

      if (navigator.clipboard && window.isSecureContext) {
        navigator.clipboard.writeText(text).then(done, legacyCopy);
      } else {
        legacyCopy();
      }

      function legacyCopy() {
        var ta = document.createElement('textarea');
        ta.value = text;
        ta.style.position = 'fixed';
        ta.style.opacity = '0';
        document.body.appendChild(ta);
        ta.select();
        try { document.execCommand('copy'); done(); }
        catch (e) { toast('Gagal menyalin. Pilih teksnya lalu tekan Ctrl+C.', true); }
        document.body.removeChild(ta);
      }
    });
  });

  /* ------------------------------------------------- Form: slug & pratinjau */

  var nameInput = document.getElementById('f-name');
  var slugInput = document.getElementById('f-slug');
  if (nameInput && slugInput) {
    var slugTouched = slugInput.value !== '';
    slugInput.addEventListener('input', function () { slugTouched = true; });
    nameInput.addEventListener('input', function () {
      if (slugTouched) return;
      slugInput.value = nameInput.value
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '');
    });
  }

  // Selaraskan color picker dengan field teks hex
  document.querySelectorAll('input[type="color"][data-sync]').forEach(function (picker) {
    var text = document.querySelector(picker.dataset.sync);
    if (!text) return;
    picker.addEventListener('input', function () { text.value = picker.value; });
    text.addEventListener('input', function () {
      if (/^#[0-9a-fA-F]{6}$/.test(text.value)) picker.value = text.value;
    });
  });

  // Pratinjau ikon sebelum diunggah
  var iconInput = document.getElementById('f-icon');
  var iconPreview = document.getElementById('icon-preview');
  if (iconInput && iconPreview) {
    iconInput.addEventListener('change', function () {
      var file = iconInput.files && iconInput.files[0];
      if (!file) return;
      var url = URL.createObjectURL(file);
      iconPreview.innerHTML = '<img src="' + url + '" alt="Pratinjau" width="96" height="96">';
    });
  }
})();
