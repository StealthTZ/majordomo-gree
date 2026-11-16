/* GREE 1.0.0 — control panel UI */
(function () {
    'use strict';

    var app = document.getElementById('gree-app');
    if (!app) {
        return;
    }

    // ------------------------------------------------------------------ setup
    var theme = document.querySelector('link#theme');
    if (theme && /dark/i.test(theme.getAttribute('href') || '')) {
        app.classList.add('gree--dark');
    }

    var API = app.getAttribute('data-api') || '';
    var STATE = {devices: [], meta: {}};
    var stateNode = document.getElementById('gree-state');
    if (stateNode) {
        try {
            STATE = JSON.parse(stateNode.textContent || '{}');
        } catch (e) {
            STATE = {devices: [], meta: {}};
        }
    }
    var META = STATE.meta || {};

    var ICON = {
        power: '<path d="M12 3v8M6.3 7.3a8 8 0 1 0 11.4 0"/>',
        refresh: '<path d="M20 11a8 8 0 0 0-14.9-4M4 13a8 8 0 0 0 14.9 4M4 4v5h5M20 20v-5h-5"/>',
        auto: '<path d="M4 19 10 5h4l6 14M7 13h10"/>',
        cool: '<path d="M12 2v20M4.9 6.5l14.2 11M4.9 17.5l14.2-11M9 3.5l3 2.5 3-2.5M9 20.5l3-2.5 3 2.5"/>',
        dry: '<path d="M12 3s-6 7-6 11a6 6 0 0 0 12 0c0-4-6-11-6-11Z"/>',
        fan: '<circle cx="12" cy="12" r="2"/><path d="M12 10c0-4 1-7 4-7 2 0 3 2 2 4-1 2-3 3-6 3ZM14 12c4 0 7 1 7 4 0 2-2 3-4 2-2-1-3-3-3-6ZM12 14c0 4-1 7-4 7-2 0-3-2-2-4 1-2 3-3 6-3ZM10 12c-4 0-7-1-7-4 0-2 2-3 4-2 2 1 3 3 3 6Z"/>',
        heat: '<circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.9 4.9l1.4 1.4M17.7 17.7l1.4 1.4M2 12h2M20 12h2M4.9 19.1l1.4-1.4M17.7 6.3l1.4-1.4"/>',
        turbo: '<path d="M13 2 4 14h7l-1 8 9-12h-7l1-8Z"/>',
        quiet: '<path d="M11 5 6 9H3v6h3l5 4V5ZM22 9l-6 6M16 9l6 6"/>',
        sleep: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8Z"/>',
        light: '<path d="M9 18h6M10 21h4M12 3a6 6 0 0 0-4 10.5c.7.7 1 1.5 1 2.5h6c0-1 .3-1.8 1-2.5A6 6 0 0 0 12 3Z"/>',
        health: '<circle cx="12" cy="12" r="3"/><path d="M12 2v3M12 19v3M2 12h3M19 12h3M5 5l2 2M17 17l2 2M5 19l2-2M17 7l2-2"/>',
        blow: '<path d="M3 8h10a3 3 0 1 0-3-3M3 12h15a3 3 0 1 1-3 3M3 16h7"/>',
        air: '<path d="M3 12h4l3-8 4 16 3-8h4"/>',
        energy_save: '<path d="M11 20A7 7 0 0 1 4 13c0-6 6-9 16-9 0 10-3 16-9 16ZM4 21c3-5 6-8 11-11"/>',
        eco: '<path d="M11 20A7 7 0 0 1 4 13c0-6 6-9 16-9 0 10-3 16-9 16ZM4 21c3-5 6-8 11-11"/>',
        stht: '<path d="M14 14.8V5a2 2 0 1 0-4 0v9.8a4 4 0 1 0 4 0ZM12 10v6"/>',
        thermo: '<path d="M14 14.8V5a2 2 0 1 0-4 0v9.8a4 4 0 1 0 4 0Z"/>'
    };

    var MODES = [
        {v: 0, t: 'Авто', i: 'auto'},
        {v: 1, t: 'Холод', i: 'cool'},
        {v: 2, t: 'Осушение', i: 'dry'},
        {v: 3, t: 'Вентиляция', i: 'fan'},
        {v: 4, t: 'Тепло', i: 'heat'}
    ];

    var FEATURES = [
        {p: 'turbo', t: 'Турбо', s: 'Максимальная мощность'},
        {p: 'quiet', t: 'Тихий режим', s: 'Минимальный шум'},
        {p: 'sleep', t: 'Сон', s: 'Мягкая коррекция ночью'},
        {p: 'light', t: 'Подсветка', s: 'Дисплей на блоке'},
        {p: 'health', t: 'Ионизатор', s: 'Health / Cold plasma'},
        {p: 'blow', t: 'X-Fan', s: 'Просушка после выключения'},
        {p: 'air', t: 'Свежий воздух', s: 'Приточный клапан'},
        {p: 'energy_save', t: 'Энергосбережение', s: 'Экономичный режим'},
        {p: 'eco', t: 'ECO', s: 'Экономичный режим'},
        {p: 'stht', t: 'Обогрев +8 °C', s: 'Защита от замерзания'}
    ];

    function svg(name) {
        return '<svg viewBox="0 0 24 24" aria-hidden="true">' + (ICON[name] || '') + '</svg>';
    }

    function esc(s) {
        return String(s === null || s === undefined ? '' : s)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    function num(v) {
        var n = parseFloat(v);
        return isNaN(n) ? null : n;
    }

    function has(dev, prop) {
        return dev && dev.props && Object.prototype.hasOwnProperty.call(dev.props, prop) && dev.props[prop] !== '';
    }

    function writable(dev, prop) {
        return dev && dev.writable && dev.writable.indexOf(prop) !== -1;
    }

    function isOn(dev, prop) {
        return has(dev, prop) && num(dev.props[prop]) > 0;
    }

    function findDevice(id) {
        id = parseInt(id, 10);
        for (var i = 0; i < STATE.devices.length; i++) {
            if (STATE.devices[i].id === id) {
                return STATE.devices[i];
            }
        }
        return null;
    }

    function tempRange(dev) {
        return dev && dev.type === 'CH' ? [16, 31] : [16, 30];
    }

    function ago(seconds) {
        if (seconds === null || seconds === undefined) {
            return 'нет данных';
        }
        if (seconds < 10) {
            return 'обновлено только что';
        }
        if (seconds < 60) {
            return 'обновлено ' + seconds + ' с назад';
        }
        if (seconds < 3600) {
            return 'обновлено ' + Math.floor(seconds / 60) + ' мин назад';
        }
        if (seconds < 86400) {
            return 'обновлено ' + Math.floor(seconds / 3600) + ' ч назад';
        }
        return 'обновлено ' + Math.floor(seconds / 86400) + ' дн назад';
    }

    function statusOf(dev) {
        if (dev.type === 'Gree' && !dev.bound) {
            return {cls: 'is-paused', text: 'нет ключа устройства'};
        }
        if (dev.chtime === 'none') {
            return {cls: 'is-paused', text: 'опрос выключен · ' + ago(dev.age)};
        }
        if (!dev.updated) {
            return {cls: 'is-offline', text: 'ещё не отвечал'};
        }
        return dev.online ? {cls: 'is-online', text: ago(dev.age)} : {cls: 'is-offline', text: 'не в сети · ' + ago(dev.age)};
    }

    function optionLabel(prop, value) {
        var m = META[prop];
        if (m && m.options && m.options[value] !== undefined) {
            return m.options[value];
        }
        return String(value);
    }

    // ------------------------------------------------------------------ toast
    var toastTimer = null;

    function toast(text, isError) {
        var el = document.getElementById('gree-toast');
        if (!el) {
            return;
        }
        el.textContent = text;
        el.classList.toggle('is-error', !!isError);
        el.classList.add('is-visible');
        clearTimeout(toastTimer);
        toastTimer = setTimeout(function () {
            el.classList.remove('is-visible');
        }, isError ? 5000 : 2200);
    }

    // ------------------------------------------------------------------ api
    function api(op, params) {
        if (!API) {
            return Promise.reject(new Error('Управление доступно в панели управления'));
        }
        var url = API + encodeURIComponent(op);
        params = params || {};
        Object.keys(params).forEach(function (k) {
            url += '&' + encodeURIComponent(k) + '=' + encodeURIComponent(params[k]);
        });
        url += '&_=' + Date.now();
        return fetch(url, {credentials: 'same-origin', headers: {'Accept': 'application/json'}})
            .then(function (r) {
                return r.text();
            })
            .then(function (text) {
                var data;
                try {
                    data = JSON.parse(text);
                } catch (e) {
                    throw new Error('Панель вернула не JSON. Обновите страницу, возможно истекла сессия.');
                }
                mergeState(data);
                if (!data.ok) {
                    throw new Error(data.error || 'Ошибка');
                }
                return data;
            });
    }

    function mergeState(data) {
        if (!data || !data.devices) {
            return;
        }
        data.devices.forEach(function (fresh) {
            var found = false;
            for (var i = 0; i < STATE.devices.length; i++) {
                if (STATE.devices[i].id === fresh.id) {
                    STATE.devices[i] = fresh;
                    found = true;
                }
            }
            if (!found) {
                STATE.devices.push(fresh);
            }
        });
        var pill = document.getElementById('gree-cycle');
        if (pill && typeof data.cycle === 'boolean' && !pill.classList.contains('is-busy')) {
            pill.className = 'gree-pill ' + (data.cycle ? 'gree-pill--ok' : 'gree-pill--bad');
            pill.innerHTML = '<i></i>' + (data.cycle ? 'Опрос работает' : 'Опрос остановлен' + (pill.tagName === 'BUTTON' ? ' · запустить' : ''));
            if (pill.tagName === 'BUTTON') {
                pill.disabled = data.cycle;
            }
        }
        renderAll();
    }

    // Send a value with an optimistic update; the server answer replaces it.
    function send(dev, prop, value) {
        var before = dev.props[prop];
        dev.props[prop] = String(value);
        renderAll();
        return api('set', {id: dev.id, prop: prop, value: value})
            .catch(function (e) {
                dev.props[prop] = before;
                renderAll();
                toast(e.message, true);
            });
    }

    // temperature steps are collected and sent once the user stops clicking
    var tempTimers = {};

    function stepTemperature(dev, delta) {
        var range = tempRange(dev);
        var current = num(dev.props.temperature);
        if (current === null) {
            current = 24;
        }
        var target = Math.max(range[0], Math.min(range[1], Math.round(current) + delta));
        if (target === current) {
            return;
        }
        dev.props.temperature = String(target);
        renderAll();
        clearTimeout(tempTimers[dev.id]);
        tempTimers[dev.id] = setTimeout(function () {
            api('set', {id: dev.id, prop: 'temperature', value: target}).catch(function (e) {
                toast(e.message, true);
                api('state', {id: dev.id}).catch(function () {});
            });
        }, 650);
    }

    // ------------------------------------------------------------------ list page
    function renderCard(card) {
        var dev = findDevice(card.getAttribute('data-id'));
        if (!dev) {
            return;
        }
        var on = isOn(dev, 'power');
        var mode = has(dev, 'ac_mode') ? num(dev.props.ac_mode) : null;
        var st = statusOf(dev);

        card.classList.toggle('is-on', on);
        card.classList.toggle('is-offline', st.cls === 'is-offline');
        if (mode !== null && dev.type !== 'CH') {
            card.setAttribute('data-mode', mode);
        } else {
            card.removeAttribute('data-mode');
        }

        var power = card.querySelector('[data-gree-power]');
        if (power) {
            power.classList.toggle('is-on', on);
            power.disabled = !API || !writable(dev, 'power') || !has(dev, 'power');
            power.setAttribute('aria-pressed', on ? 'true' : 'false');
        }

        var t = card.querySelector('[data-f="temperature"]');
        if (t) {
            t.textContent = has(dev, 'temperature') ? Math.round(num(dev.props.temperature)) : '—';
        }
        var range = tempRange(dev);
        var tv = num(dev.props.temperature);
        var minus = card.querySelector('[data-gree-step="-1"]');
        var plus = card.querySelector('[data-gree-step="1"]');
        var canTemp = !!API && writable(dev, 'temperature') && has(dev, 'temperature');
        if (minus) {
            minus.disabled = !canTemp || tv <= range[0];
        }
        if (plus) {
            plus.disabled = !canTemp || tv >= range[1];
        }

        var room = card.querySelector('[data-f="room"]');
        if (room) {
            var rt = has(dev, 'indoorTemperature') ? num(dev.props.indoorTemperature) : null;
            room.hidden = rt === null;
            if (rt !== null) {
                room.querySelector('span').textContent = 'в комнате ' + rt + ' °C';
            }
        }

        var chips = card.querySelector('[data-f="chips"]');
        if (chips) {
            var html = '';
            if (!has(dev, 'power')) {
                html += '<span class="gree-tag">состояние неизвестно</span>';
            } else if (!on) {
                html += '<span class="gree-tag">выключен</span>';
            } else {
                if (mode !== null) {
                    var m = dev.type === 'CH' ? null : MODES[mode];
                    html += '<span class="gree-tag gree-tag--mode">' + (m ? svg(m.i) + esc(optionLabel('ac_mode', mode)) : 'режим ' + esc(mode)) + '</span>';
                }
                if (has(dev, 'fan_speed')) {
                    html += '<span class="gree-tag">' + svg('fan') + 'вентилятор: ' + esc(dev.type === 'CH' ? dev.props.fan_speed : optionLabel('fan_speed', num(dev.props.fan_speed)).toLowerCase()) + '</span>';
                }
                FEATURES.forEach(function (f) {
                    if (isOn(dev, f.p)) {
                        html += '<span class="gree-tag">' + esc(f.t) + '</span>';
                    }
                });
            }
            chips.innerHTML = html;
        }

        var status = card.querySelector('[data-f="status"]');
        if (status) {
            status.className = 'gree-status ' + st.cls;
            status.querySelector('span').textContent = st.text;
        }
    }

    function initList() {
        var cards = app.querySelectorAll('.gree-card[data-id]');
        if (!cards.length) {
            return;
        }
        app.addEventListener('click', function (e) {
            var card = e.target.closest('.gree-card[data-id]');
            if (!card) {
                return;
            }
            var dev = findDevice(card.getAttribute('data-id'));
            if (!dev) {
                return;
            }
            if (e.target.closest('[data-gree-power]')) {
                send(dev, 'power', isOn(dev, 'power') ? 0 : 1);
            }
            var step = e.target.closest('[data-gree-step]');
            if (step) {
                stepTemperature(dev, parseInt(step.getAttribute('data-gree-step'), 10));
            }
        });
    }

    // ------------------------------------------------------------------ remote page
    function renderRemote(root) {
        var dev = findDevice(root.getAttribute('data-id'));
        if (!dev) {
            root.innerHTML = '<div class="gree-note">Нет данных об устройстве.</div>';
            return;
        }
        // keep focus on the select the user is working with
        var focused = document.activeElement && root.contains(document.activeElement) ? document.activeElement.getAttribute('data-prop') : null;

        var on = isOn(dev, 'power');
        var isGree = dev.type !== 'CH';
        var mode = has(dev, 'ac_mode') ? num(dev.props.ac_mode) : null;
        var range = tempRange(dev);
        var temp = has(dev, 'temperature') ? Math.round(num(dev.props.temperature)) : null;
        var room = has(dev, 'indoorTemperature') ? num(dev.props.indoorTemperature) : null;
        var st = statusOf(dev);
        var fill = temp === null ? 0 : Math.round((temp - range[0]) / (range[1] - range[0]) * 270);
        var canTemp = writable(dev, 'temperature') && temp !== null;
        var settingsLink = document.getElementById('gree-settings-link');
        var html = '';

        // left column: dial
        html += '<div class="gree-dialcard' + (on ? ' is-on' : '') + '"' + (mode !== null && isGree ? ' data-mode="' + mode + '"' : '') + '>';
        html += '<div class="gree-dialcard__top"><span class="gree-status ' + st.cls + '"><i></i><span>' + esc(st.text) + '</span></span>';
        html += '<button type="button" class="gree-icon-btn" data-act="poll" title="Опросить кондиционер сейчас">' + svg('refresh') + '</button></div>';
        html += '<div class="gree-dial" style="--hv-fill:' + fill + 'deg"><div class="gree-dial__ring"></div><div class="gree-dial__center">';
        html += '<span class="gree-dial__label">' + (on ? 'Поддерживать' : 'Выключен') + '</span>';
        html += '<span class="gree-dial__value">' + (temp === null ? '—' : temp) + '<sup>°C</sup></span>';
        html += '<span class="gree-dial__room">' + (room !== null ? 'В комнате <b>' + room + ' °C</b>' : (mode !== null && isGree ? esc(optionLabel('ac_mode', mode)) : '&nbsp;')) + '</span>';
        html += '</div></div>';
        html += '<div class="gree-dial__controls">';
        html += '<button type="button" class="gree-round" data-act="step" data-delta="-1" aria-label="Холоднее"' + (!canTemp || temp <= range[0] ? ' disabled' : '') + '>&minus;</button>';
        html += '<button type="button" class="gree-power' + (on ? ' is-on' : '') + '" data-act="power" aria-pressed="' + on + '" title="Включить / выключить"' + (writable(dev, 'power') && has(dev, 'power') ? '' : ' disabled') + '>' + svg('power') + '</button>';
        html += '<button type="button" class="gree-round" data-act="step" data-delta="1" aria-label="Теплее"' + (!canTemp || temp >= range[1] ? ' disabled' : '') + '>+</button>';
        html += '</div>';
        html += '<div class="gree-dialcard__foot"><span class="gree-hint" style="margin:0">' + esc(dev.ip) + (dev.encryption === 'GCM' && isGree ? ' · протокол v2' : '') + '</span>';
        html += '<span class="gree-hint" style="margin:0">Диапазон ' + range[0] + '–' + range[1] + ' °C</span></div>';
        html += '</div>';

        // right column
        html += '<div class="gree-controls">';

        if (isGree && !dev.bound) {
            html += '<div class="gree-note">Ключ устройства ещё не получен, кондиционер не примет команды. ' +
                (settingsLink ? '<a href="' + esc(settingsLink.href) + '">Откройте вкладку «Подключение»</a> и нажмите «Получить ключ».' : '') + '</div>';
        } else if (!has(dev, 'power')) {
            html += '<div class="gree-note">Состояние кондиционера ещё не прочитано. Нажмите кнопку обновления над шкалой температуры.</div>';
        }

        if (mode !== null) {
            html += '<div class="gree-block"><h3 class="gree-block__title">Режим<span>' + esc(isGree ? optionLabel('ac_mode', mode) : 'код ' + mode) + '</span></h3>';
            if (isGree) {
                html += '<div class="gree-modes">';
                MODES.forEach(function (m) {
                    html += '<button type="button" class="gree-mode' + (m.v === mode ? ' is-active' : '') + '" data-mode="' + m.v + '" data-act="set" data-prop="ac_mode" data-value="' + m.v + '">' + svg(m.i) + esc(m.t) + '</button>';
                });
                html += '</div>';
            } else {
                html += selectHtml(dev, 'ac_mode', range8(0, 7), function (v) { return 'Код ' + v; });
            }
            html += '</div>';
        }

        if (has(dev, 'fan_speed')) {
            var fan = num(dev.props.fan_speed);
            html += '<div class="gree-block"><h3 class="gree-block__title">Вентилятор<span>' + esc(isGree ? optionLabel('fan_speed', fan) : 'код ' + fan) + '</span></h3>';
            if (isGree) {
                html += '<div class="gree-fan">';
                for (var s = 0; s <= 5; s++) {
                    var label = s === 0 ? 'Авто' : '';
                    var bars = '';
                    if (s > 0) {
                        for (var b = 1; b <= 5; b++) {
                            bars += '<i class="' + (b <= s ? 'on' : '') + '" style="height:' + (4 + b * 3) + 'px"></i>';
                        }
                    }
                    html += '<button type="button" class="' + (s === 0 ? 'gree-fan__auto' : '') + (s === fan ? ' is-active' : '') + '" data-act="set" data-prop="fan_speed" data-value="' + s + '" title="' + esc(optionLabel('fan_speed', s)) + '">' + (label || bars) + '</button>';
                }
                html += '</div>';
            } else {
                html += selectHtml(dev, 'fan_speed', [0, 1, 2, 3, 4, 5, 6, 8], function (v) { return v === 0 ? 'Авто (0)' : 'Скорость ' + v; });
            }
            html += '</div>';
        }

        if (has(dev, 'fan_direction') || has(dev, 'fan_directionh')) {
            html += '<div class="gree-block"><h3 class="gree-block__title">Направление потока</h3><div class="gree-selects">';
            if (has(dev, 'fan_direction')) {
                html += '<div><label>Вверх / вниз</label>' + selectHtml(dev, 'fan_direction', isGree ? range8(0, 11) : range8(0, 15), isGree ? null : function (v) { return 'Код ' + v; }) + '</div>';
            }
            if (has(dev, 'fan_directionh')) {
                html += '<div><label>Влево / вправо</label>' + selectHtml(dev, 'fan_directionh', range8(0, 6), null) + '</div>';
            }
            html += '</div></div>';
        }

        var feats = '';
        FEATURES.forEach(function (f) {
            if (!has(dev, f.p) || !writable(dev, f.p)) {
                return;
            }
            var value = num(dev.props[f.p]);
            var active = value > 0;
            var sub = f.s;
            if (f.p === 'quiet' && value === 1) {
                sub = 'Автоматический';
            }
            feats += '<button type="button" class="gree-chip' + (active ? ' is-on' : '') + '" data-act="toggle" data-prop="' + f.p + '" aria-pressed="' + active + '">' +
                '<span class="gree-chip__icon">' + svg(f.p) + '</span><span class="gree-chip__text">' + esc(f.t) + '<small>' + esc(sub) + '</small></span><span class="gree-switch"></span></button>';
        });
        if (feats) {
            html += '<div class="gree-block"><h3 class="gree-block__title">Функции</h3><div class="gree-features">' + feats + '</div></div>';
        }

        var ro = '';
        Object.keys(dev.props).forEach(function (p) {
            if (writable(dev, p) || p === 'indoorTemperature' || dev.props[p] === '') {
                return;
            }
            var label = META[p] ? META[p].label : p;
            ro += '<div><small>' + esc(label) + '</small><b>' + esc(dev.props[p]) + '</b></div>';
        });
        if (ro) {
            html += '<details class="gree-block"><summary class="gree-block__title" style="margin:0;cursor:pointer">Служебные значения</summary><div class="gree-readonly" style="margin-top:12px">' + ro + '</div></details>';
        }

        html += '</div>';

        var openDetails = root.querySelector('details[open]') !== null;
        root.innerHTML = html;
        if (openDetails) {
            var d = root.querySelector('details');
            if (d) {
                d.open = true;
            }
        }
        if (focused) {
            var el = root.querySelector('[data-prop="' + focused + '"]');
            if (el && el.tagName === 'SELECT') {
                el.focus();
            }
        }
    }

    function range8(a, b) {
        var out = [];
        for (var i = a; i <= b; i++) {
            out.push(i);
        }
        return out;
    }

    function selectHtml(dev, prop, values, labelFn) {
        var current = num(dev.props[prop]);
        var html = '<select data-act="select" data-prop="' + prop + '"' + (writable(dev, prop) ? '' : ' disabled') + '>';
        var known = false;
        values.forEach(function (v) {
            if (v === current) {
                known = true;
            }
            html += '<option value="' + v + '"' + (v === current ? ' selected' : '') + '>' + esc(labelFn ? labelFn(v) : optionLabel(prop, v)) + '</option>';
        });
        if (!known && current !== null) {
            html += '<option value="' + current + '" selected>Код ' + esc(current) + '</option>';
        }
        return html + '</select>';
    }

    function initRemote(root) {
        root.addEventListener('click', function (e) {
            var el = e.target.closest('[data-act]');
            if (!el || el.disabled) {
                return;
            }
            var dev = findDevice(root.getAttribute('data-id'));
            if (!dev) {
                return;
            }
            var act = el.getAttribute('data-act');
            if (act === 'power') {
                send(dev, 'power', isOn(dev, 'power') ? 0 : 1);
            } else if (act === 'step') {
                stepTemperature(dev, parseInt(el.getAttribute('data-delta'), 10));
            } else if (act === 'set') {
                send(dev, el.getAttribute('data-prop'), el.getAttribute('data-value'));
            } else if (act === 'toggle') {
                var prop = el.getAttribute('data-prop');
                var onValue = (prop === 'quiet' && dev.type !== 'CH') ? 2 : 1;
                send(dev, prop, isOn(dev, prop) ? 0 : onValue);
            } else if (act === 'poll') {
                el.classList.add('is-busy');
                el.disabled = true;
                api('poll', {id: dev.id})
                    .then(function () { toast('Состояние обновлено'); })
                    .catch(function (err) { toast(err.message, true); });
            }
        });
        root.addEventListener('change', function (e) {
            var el = e.target.closest('select[data-act="select"]');
            if (!el) {
                return;
            }
            var dev = findDevice(root.getAttribute('data-id'));
            if (dev) {
                send(dev, el.getAttribute('data-prop'), el.value);
            }
        });
    }

    // ------------------------------------------------------------------ settings form
    function initSettings(form) {
        var devtype = form.querySelector('[name="devtype"]');

        function syncType() {
            var checked = form.querySelector('[name="type"]:checked');
            var type = checked ? checked.value : 'Gree';
            form.classList.toggle('is-ch', type === 'CH');
            if (devtype) {
                var v = devtype.value.trim().toLowerCase();
                if (type === 'Gree' && v !== '0x2711') {
                    devtype.value = '0x2711';
                }
                if (type === 'CH' && v !== '0x202') {
                    devtype.value = '0x202';
                }
            }
        }

        form.addEventListener('change', function (e) {
            if (e.target.name === 'type') {
                syncType();
            }
        });
        syncType();

        var pairBtn = form.querySelector('[data-gree-pair]');
        if (pairBtn) {
            pairBtn.addEventListener('click', function () {
                var ip = form.querySelector('[name="ip"]').value.trim();
                var mac = form.querySelector('[name="mac"]').value.trim();
                var enc = form.querySelector('[name="encryption"]:checked');
                if (!ip || mac.replace(/[^0-9a-f]/gi, '').length !== 12) {
                    toast('Укажите IP и MAC кондиционера', true);
                    return;
                }
                pairBtn.classList.add('is-busy');
                pairBtn.textContent = 'Запрос…';
                api('pair', {id: pairBtn.getAttribute('data-gree-pair') || 0, ip: ip, mac: mac, encryption: enc ? enc.value : 'ECB'})
                    .then(function (data) {
                        form.querySelector('[name="keys"]').value = data.key;
                        var radio = form.querySelector('[name="encryption"][value="' + data.encryption + '"]');
                        if (radio) {
                            radio.checked = true;
                        }
                        toast('Ключ получен, протокол ' + (data.encryption === 'GCM' ? 'v2' : 'v1'));
                    })
                    .catch(function (e) {
                        toast(e.message, true);
                    })
                    .then(function () {
                        pairBtn.classList.remove('is-busy');
                        pairBtn.textContent = 'Получить ключ';
                    });
            });
        }
    }

    // ------------------------------------------------------------------ boot
    var remote = document.getElementById('gree-remote');

    function renderAll() {
        Array.prototype.forEach.call(app.querySelectorAll('.gree-card[data-id]'), renderCard);
        if (remote) {
            var active = document.activeElement;
            // never rebuild under an open drop-down
            if (!(active && active.tagName === 'SELECT' && remote.contains(active))) {
                renderRemote(remote);
            }
        }
    }

    initList();
    if (remote) {
        initRemote(remote);
    }
    var settings = document.getElementById('gree-settings');
    if (settings) {
        initSettings(settings);
    }

    var cycleBtn = app.querySelector('[data-gree-cycle-restart]');
    if (cycleBtn) {
        cycleBtn.addEventListener('click', function () {
            if (cycleBtn.disabled) {
                return;
            }
            cycleBtn.classList.add('is-busy');
            cycleBtn.disabled = true;
            cycleBtn.innerHTML = '<i></i>Запуск, до минуты…';
            api('cycle_restart')
                .then(function () {
                    toast('Команда отправлена менеджеру циклов, опрос стартует в течение минуты');
                    var tries = 0;
                    var timer = setInterval(function () {
                        tries++;
                        api('state', {id: 0}).then(function (d) {
                            if (d.cycle || tries >= 12) {
                                clearInterval(timer);
                                cycleBtn.classList.remove('is-busy');
                                mergeState(d);
                                if (!d.cycle) {
                                    toast('Цикл не запустился. Проверьте, что работает cycle.php (сервис majordomo)', true);
                                }
                            }
                        }).catch(function () {});
                    }, 10000);
                })
                .catch(function (e) {
                    cycleBtn.classList.remove('is-busy');
                    cycleBtn.disabled = false;
                    toast(e.message, true);
                });
        });
    }

    var pollAll = app.querySelector('[data-gree-poll-all]');
    if (pollAll) {
        pollAll.addEventListener('click', function () {
            pollAll.classList.add('is-busy');
            api('poll_all')
                .then(function () { toast('Все кондиционеры опрошены'); })
                .catch(function (e) { toast(e.message, true); })
                .then(function () { pollAll.classList.remove('is-busy'); });
        });
    }

    if (STATE.devices.length && (remote || app.querySelector('.gree-card[data-id]'))) {
        renderAll();
        // light refresh from the database; the device itself is polled by the cycle
        setInterval(function () {
            if (document.hidden) {
                return;
            }
            var id = remote ? remote.getAttribute('data-id') : 0;
            if (API) {
                api('state', {id: id}).catch(function () {});
            }
        }, 10000);
    }
})();
