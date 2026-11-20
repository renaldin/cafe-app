"use client";

/** Notifikasi suara tanpa file audio (WebAudio), aman untuk static hosting. */
let ctx: AudioContext | null = null;

export function unlockAudio() {
  try {
    if (!ctx) ctx = new (window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext)();
    if (ctx.state === "suspended") void ctx.resume();
    return true;
  } catch {
    return false;
  }
}

function tone(freq: number, start: number, dur: number, gain = 0.25) {
  if (!ctx) return;
  const osc = ctx.createOscillator();
  const g = ctx.createGain();
  osc.type = "sine";
  osc.frequency.value = freq;
  g.gain.setValueAtTime(0, ctx.currentTime + start);
  g.gain.linearRampToValueAtTime(gain, ctx.currentTime + start + 0.02);
  g.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + start + dur);
  osc.connect(g).connect(ctx.destination);
  osc.start(ctx.currentTime + start);
  osc.stop(ctx.currentTime + start + dur + 0.05);
}

export function playNewOrder() {
  if (!unlockAudio()) return;
  tone(880, 0, 0.18);
  tone(1175, 0.2, 0.18);
  tone(1568, 0.4, 0.35);
}

export function playCall() {
  if (!unlockAudio()) return;
  tone(660, 0, 0.25);
  tone(660, 0.35, 0.25);
}

export function vibrate(pattern: number | number[] = [120, 60, 120]) {
  try {
    navigator.vibrate?.(pattern);
  } catch {
    /* abaikan */
  }
}

/** Kedipkan judul tab saat ada pesanan baru */
let flashTimer: ReturnType<typeof setInterval> | null = null;
export function flashTitle(text: string) {
  if (typeof document === "undefined") return;
  const original = document.title.replace(/^\(\d+\) |^🔔 .*? · /, "");
  if (flashTimer) clearInterval(flashTimer);
  let on = false;
  let n = 0;
  flashTimer = setInterval(() => {
    document.title = on ? original : `🔔 ${text} · ${original}`;
    on = !on;
    if (++n > 10) {
      clearInterval(flashTimer!);
      document.title = original;
    }
  }, 800);
}
