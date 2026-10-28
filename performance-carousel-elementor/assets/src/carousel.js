/*! Performance Image Carousel for Elementor v4.1 | vanilla JS, no dependencies */
(() => {
	'use strict';
	const W = window, D = document;
	const MQ = W.matchMedia && W.matchMedia('(prefers-reduced-motion: reduce)');
	const RM = () => !!(MQ && MQ.matches);
	const cl = (v, a, b) => (v < a ? a : v > b ? b : v);
	const md = (a, n) => ((a % n) + n) % n;

	class Carousel {
		constructor(r) {
			r.dataset.oicReady = '1';
			const q = (s) => r.querySelector(s);
			let c = {};
			try { c = JSON.parse(r.dataset.oic || '{}'); } catch (e) { /* defaults */ }
			c.speed = RM() ? 0 : cl(+c.speed || 0, 0, 3000);
			c.delay = cl(+c.delay || 5000, 1000, 60000);
			this.r = r; this.c = c;
			this.vp = q('.oic-viewport'); this.tr = q('.oic-track');
			this.org = [...this.tr.children]; this.n = this.org.length;
			this.fade = c.fx === 'fade';
			this.rtl = c.dir === 'rtl';
			this.i = 0; this.p = 0; this.base = 0;
			this.hold = new Set(); this.left = c.delay; this.t = 0;
			this.dots = [...r.querySelectorAll('.oic-dot')];
			this.cur = q('.oic-current'); this.bar = q('.oic-progress');
			this.pv = q('.oic-prev'); this.nx = q('.oic-next'); this.pb = q('.oic-play');
			this.org.forEach((s, k) => { s.dataset.real = k; });

			this.build();
			this.bind();

			const rb = () => this.queue();
			if (W.ResizeObserver) (this.ro = new ResizeObserver(rb)).observe(this.vp);
			else W.addEventListener('resize', rb, { passive: true });
			if (W.IntersectionObserver) {
				(this.io = new IntersectionObserver((e) => this.pz('io', !e[e.length - 1].isIntersecting))).observe(r);
			}
			this.dv = () => this.pz('doc', D.hidden);
			D.addEventListener('visibilitychange', this.dv);
			if (c.edit) this.poll = setInterval(() => { if (!r.isConnected) this.kill(); else if (this.sg() !== this.sig) this.queue(); }, 400);
			const pr = () => setTimeout(() => { this.ok = 1; this.prime(); }, 150);
			D.readyState === 'complete' ? pr() : W.addEventListener('load', pr, { once: true });
			r.classList.add('oic-ready');
			this.sch();
		}

		v(k, d) { const x = parseFloat(getComputedStyle(this.r).getPropertyValue(k)); return x === x ? x : d; }
		sg() { return [this.vp.clientWidth, this.v('--oic-show', 1), this.v('--oic-scroll', 1), this.v('--oic-gap', 0)].join('|'); }
		max() { return this.loop ? this.n - 1 : Math.max(0, Math.ceil(this.n - this.show - 1e-3)); }

		clone(o) {
			const x = o.cloneNode(true);
			x.classList.add('oic-clone');
			x.setAttribute('aria-hidden', 'true');
			x.querySelectorAll('[id]').forEach((e) => e.removeAttribute('id'));
			x.querySelectorAll('a,button').forEach((e) => {
				e.tabIndex = -1;
				if (e.hasAttribute('data-elementor-open-lightbox')) {
					e.setAttribute('data-elementor-open-lightbox', 'no');
					e.removeAttribute('data-elementor-lightbox-slideshow');
					e.dataset.oicFwd = '1';
				}
			});
			x.querySelectorAll('img').forEach((i) => { i.loading = 'lazy'; i.setAttribute('fetchpriority', 'low'); });
			return x;
		}

		build() {
			const { tr, c, n } = this;
			clearTimeout(this.ft); this.busy = 0;
			tr.replaceChildren(...this.org);
			tr.style.transform = ''; tr.style.transition = '';
			this.items = this.org;
			this.vw = this.vp.clientWidth;
			this.sig = this.sg();
			this.show = 1; this.mv = 1;
			this.loop = !!c.loop && n > 1;
			if (!this.vw) return; // hidden (tab/popup): ResizeObserver rebuilds when visible
			if (this.fade) { this.i = cl(this.i, 0, n - 1); this.paint(); return; }
			const gap = parseFloat(getComputedStyle(tr).columnGap) || 0;
			this.gap = gap;
			this.show = cl(this.v('--oic-show', 1), 1, 10);
			this.mv = cl(Math.round(this.v('--oic-scroll', 1)), 1, 10);
			const b = (this.base = this.loop ? Math.ceil(this.show) + this.mv : 0);
			if (b) {
				const a = D.createDocumentFragment(), z = D.createDocumentFragment();
				for (let j = b; j > 0; j--) a.append(this.clone(this.org[md(-j, n)]));
				for (let j = 0; j < b; j++) z.append(this.clone(this.org[j % n]));
				tr.prepend(a); tr.append(z);
				this.items = [...tr.children];
			}
			this.i = cl(this.i, 0, this.max());
			this.p = b + this.i;
			this.sw = this.org[0].getBoundingClientRect().width;
			this.st = this.sw + gap;
			this.pos(0); this.paint();
			if (this.ok) this.prime();
		}

		pos(a, dx = 0) {
			let x = this.p * this.st;
			if (!this.loop) x = Math.min(x, Math.max(0, this.n * this.st - this.gap - this.vw));
			x = (this.rtl ? x : -x) + dx;
			const s = this.tr.style;
			s.transition = a && this.c.speed ? `transform ${this.c.speed}ms var(--oic-ease)` : 'none';
			s.transform = `translate3d(${x}px,0,0)`;
		}

		go(t, user = 1) {
			const { n, c } = this;
			if (n < 2 || (this.busy && !this.fade)) return;
			if (user && c.pi) this.hold.add('user');
			const to = this.loop ? md(t, n) : cl(t, 0, this.max());
			if (to === this.i) { this.sch(); return; }
			const prev = this.i;
			this.i = to;
			clearTimeout(this.t); this.t = 0; this.left = c.delay;
			this.r.classList.add('oic-anim');
			if (this.fade) {
				this.org.forEach((s) => s.classList.remove('is-leaving'));
				this.org[prev].classList.add('is-leaving');
				clearTimeout(this.lt);
				this.lt = setTimeout(() => this.org[prev].classList.remove('is-leaving'), c.speed + 80);
				this.paint(); this.prime(); this.sch();
				return;
			}
			this.p = this.base + (this.loop ? t : to);
			this.busy = 1;
			this.pos(1); this.paint(); this.prime();
			if (c.speed) this.ft = setTimeout(() => this.end(), c.speed + 40);
			else this.end();
		}

		end() {
			this.busy = 0;
			if (this.loop && (this.p < this.base || this.p >= this.base + this.n)) { this.p = this.base + this.i; this.pos(0); }
			this.sch();
		}

		rel(s) {
			let d = +s.dataset.real - this.i;
			if (this.loop) { d = md(d, this.n); if (d > this.n / 2) d -= this.n; }
			return d;
		}

		paint() {
			const { i, n } = this, vc = Math.ceil(this.show - 1e-3);
			this.dots.forEach((d, k) => {
				const a = k === i;
				d.classList.toggle('is-active', a);
				d.setAttribute('aria-current', a ? 'true' : 'false');
				if (!this.fade && !this.loop) d.hidden = k > this.max();
			});
			if (this.cur) this.cur.textContent = i + 1;
			if (this.bar) { this.bar.style.setProperty('--oic-p', ((i + 1) / n) * 100 + '%'); this.bar.setAttribute('aria-valuenow', i + 1); }
			if (!this.loop) {
				if (this.pv) this.pv.disabled = i <= 0;
				if (this.nx) this.nx.disabled = i >= this.max();
			}
			// Clone + original of the same slide share state, so normalising the loop never replays animations.
			this.items.forEach((s) => {
				const d = this.rel(s), act = d === 0;
				s.classList.toggle('is-active', act);
				if (s.classList.contains('oic-clone')) return;
				const on = this.fade ? act : d >= 0 && d < vc;
				s.setAttribute('aria-hidden', on ? 'false' : 'true');
				s.querySelectorAll('a,button').forEach((e) => { e.tabIndex = on ? 0 : -1; });
			});
		}

		prime() {
			if (!this.ok || !this.items) return;
			const a = this.p - this.mv, b = this.p + Math.ceil(this.show) + this.mv;
			this.items.forEach((s, k) => {
				const near = this.fade ? Math.abs(this.rel(s)) <= 1 : k >= a && k <= b;
				if (near) s.querySelectorAll('img[loading="lazy"]').forEach((im) => { im.loading = 'eager'; });
			});
		}

		pz(k, on) { on ? this.hold.add(k) : this.hold.delete(k); this.sch(); }

		sch() {
			const { c, r, hold } = this;
			if (!c.autoplay || this.n < 2 || RM()) return;
			const done = !this.loop && this.i >= this.max();
			const off = hold.size > 0 || this.busy || done;
			r.classList.toggle('is-paused', off);
			r.classList.toggle('is-stopped', hold.has('user') || hold.has('btn') || done);
			if (off) {
				if (this.t) { clearTimeout(this.t); this.t = 0; this.left -= Date.now() - this.t0; }
				return;
			}
			if (!this.t) {
				this.t0 = Date.now();
				this.t = setTimeout(() => { this.t = 0; this.go(this.i + this.mv, 0); }, Math.max(0, this.left));
			}
		}

		bind() {
			const { r, vp, c } = this;
			const step = (f) => this.go(this.i + (f ? this.mv : -this.mv));
			if (this.pv) this.pv.addEventListener('click', () => step(0));
			if (this.nx) this.nx.addEventListener('click', () => step(1));
			this.dots.forEach((d) => d.addEventListener('click', () => this.go(+d.dataset.index)));
			if (this.pb) {
				this.pb.addEventListener('click', () => {
					const was = this.hold.has('btn') || this.hold.has('user');
					if (was) { this.hold.delete('btn'); this.hold.delete('user'); } else this.hold.add('btn');
					this.pb.setAttribute('aria-pressed', was ? 'false' : 'true');
					this.sch();
				});
			}
			if (c.ph) {
				r.addEventListener('pointerenter', (e) => { if (e.pointerType === 'mouse') this.pz('hv', 1); });
				r.addEventListener('pointerleave', () => this.pz('hv', 0));
			}
			r.addEventListener('focusin', (e) => { try { if (e.target.matches(':focus-visible')) this.pz('fc', 1); } catch (_) { /* old browsers */ } });
			r.addEventListener('focusout', (e) => { if (!r.contains(e.relatedTarget)) this.pz('fc', 0); });
			r.addEventListener('keydown', (e) => {
				if ((e.key !== 'ArrowLeft' && e.key !== 'ArrowRight') || e.target.closest('input,textarea,select')) return;
				e.preventDefault();
				step((e.key === 'ArrowRight') !== this.rtl);
			});
			this.tr.addEventListener('click', (e) => {
				if (this.sup) { e.preventDefault(); e.stopPropagation(); return; }
				const a = e.target.closest('[data-oic-fwd]');
				if (!a) return;
				e.preventDefault();
				const o = this.org[+a.closest('.oic-slide').dataset.real];
				const l = o && o.querySelector('a[data-elementor-open-lightbox]');
				if (l) l.click();
			}, true);
			if (c.wheel) {
				vp.addEventListener('wheel', (e) => {
					if (Math.abs(e.deltaX) <= Math.abs(e.deltaY)) return;
					e.preventDefault();
					if (this.wl || Math.abs(e.deltaX) < 10) return;
					this.wl = 1; setTimeout(() => { this.wl = 0; }, c.speed + 300);
					step(e.deltaX > 0 !== this.rtl);
				}, { passive: false });
			}
			if (!c.touch) return;
			vp.addEventListener('dragstart', (e) => e.preventDefault());
			vp.addEventListener('pointerdown', (e) => {
				if (this.busy || e.button > 0 || this.n < 2 || e.target.closest('button,input,select,textarea')) return;
				this.dg = { id: e.pointerId, x: e.clientX, y: e.clientY, dx: 0, on: 0, t: e.timeStamp };
			});
			vp.addEventListener('pointermove', (e) => {
				const d = this.dg;
				if (!d || d.id !== e.pointerId) return;
				const dx = e.clientX - d.x, dy = e.clientY - d.y;
				if (!d.on) {
					if (Math.abs(dy) > 10 && Math.abs(dy) > Math.abs(dx)) { this.dg = 0; return; }
					if (Math.abs(dx) < 7) return;
					d.on = 1;
					try { vp.setPointerCapture(d.id); } catch (_) { /* ignore */ }
					this.pz('dg', 1); r.classList.add('is-drag');
				}
				d.dx = dx;
				if (!this.fade) {
					const back = this.rtl ? dx < 0 : dx > 0;
					this.pos(0, !this.loop && ((this.i <= 0 && back) || (this.i >= this.max() && !back)) ? dx * 0.3 : dx);
				}
			});
			const up = (e) => {
				const d = this.dg;
				if (!d || e.pointerId !== d.id) return;
				this.dg = 0;
				if (!d.on) return;
				r.classList.remove('is-drag'); this.pz('dg', 0);
				this.sup = 1; setTimeout(() => { this.sup = 0; }, 0);
				const ax = Math.abs(d.dx), v = ax / (e.timeStamp - d.t || 1);
				if (e.type !== 'pointercancel' && (ax > Math.max(40, (this.st || this.vw) * 0.15) || (v > 0.45 && ax > 20))) step(this.rtl ? d.dx > 0 : d.dx < 0);
				if (!this.fade && !this.busy) this.pos(1);
			};
			['pointerup', 'pointercancel', 'lostpointercapture'].forEach((t) => vp.addEventListener(t, up));
		}

		queue() {
			if (this.qd) return;
			this.qd = requestAnimationFrame(() => {
				this.qd = 0;
				if (!this.r.isConnected) this.kill();
				else if (this.sg() !== this.sig) { this.build(); this.sch(); }
			});
		}

		kill() {
			clearInterval(this.poll); clearTimeout(this.t); clearTimeout(this.ft);
			if (this.ro) this.ro.disconnect();
			if (this.io) this.io.disconnect();
			D.removeEventListener('visibilitychange', this.dv);
		}
	}

	const init = (s) => {
		const x = s && s.nodeType ? s : D;
		if (x.matches && x.matches('.oic-carousel:not([data-oic-ready])')) new Carousel(x);
		if (x.querySelectorAll) x.querySelectorAll('.oic-carousel:not([data-oic-ready])').forEach((e) => new Carousel(e));
	};
	W.PCECarousel = { init };
	D.readyState === 'loading' ? D.addEventListener('DOMContentLoaded', () => init(D), { once: true }) : init(D);
	const hook = () => {
		const h = W.elementorFrontend && W.elementorFrontend.hooks;
		if (h) h.addAction('frontend/element_ready/pce_image_carousel.default', ($s) => init($s && $s[0] ? $s[0] : $s));
	};
	W.elementorFrontend && W.elementorFrontend.hooks ? hook() : W.addEventListener('elementor/frontend/init', hook, { once: true });
})();
