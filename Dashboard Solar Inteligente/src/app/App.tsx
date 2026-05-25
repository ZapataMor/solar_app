import { useEffect, useMemo, useState } from "react";
import { motion } from "motion/react";
import { Area, AreaChart, ResponsiveContainer, Tooltip } from "recharts";
import { CloudSun, RadioTower, TrendingUp, Zap } from "lucide-react";

type Level = "high" | "medium" | "low";

const CFG = {
  high: {
    label: "Radiacion alta",
    sublabel: "Produccion optima",
    statusText: "Sistema estable",
    skyTag: "Cielo despejado",
    radiation: 847,
    production: 12.4,
    efficiency: 94,
    cloudiness: 8,
    color: "#ffd05c",
    chartColor: "#ffb703",
    skyFrom: "#10223a",
    skyMid: "#476f8d",
    skyTo: "#8297a8",
    sunOpacity: 0.96,
    cloudOpacity: 0.18,
    rainVisible: false,
    particles: true,
    trendPeak: 900,
  },
  medium: {
    label: "Radiacion moderada",
    sublabel: "Produccion estable",
    statusText: "Produccion variable",
    skyTag: "Parcialmente nublado",
    radiation: 412,
    production: 7.1,
    efficiency: 58,
    cloudiness: 45,
    color: "#ffc20a",
    chartColor: "#ff9f0a",
    skyFrom: "#17263a",
    skyMid: "#4f6f86",
    skyTo: "#7b8f9d",
    sunOpacity: 0.42,
    cloudOpacity: 0.68,
    rainVisible: false,
    particles: false,
    trendPeak: 450,
  },
  low: {
    label: "Radiacion baja",
    sublabel: "Produccion reducida",
    statusText: "Capacidad reducida",
    skyTag: "Cubierto con lluvia",
    radiation: 129,
    production: 2.6,
    efficiency: 24,
    cloudiness: 88,
    color: "#e37b61",
    chartColor: "#e37b61",
    skyFrom: "#17202c",
    skyMid: "#3d5268",
    skyTo: "#687987",
    sunOpacity: 0.04,
    cloudOpacity: 0.92,
    rainVisible: true,
    particles: false,
    trendPeak: 140,
  },
} as const;

const TREND_WEIGHTS = [0.05, 0.1, 0.22, 0.39, 0.58, 0.75, 0.88, 0.94, 0.91, 0.8, 0.62, 0.42, 0.22, 0.1, 0.05];

const CLOUDS = [
  { top: "12%", w: 230, blur: 27, baseOp: 0.72, dur: "42s", delay: "-11s" },
  { top: "24%", w: 390, blur: 42, baseOp: 0.62, dur: "60s", delay: "-23s" },
  { top: "8%", w: 170, blur: 22, baseOp: 0.52, dur: "48s", delay: "-4s" },
  { top: "38%", w: 300, blur: 35, baseOp: 0.55, dur: "68s", delay: "-34s" },
];

const RAIN = Array.from({ length: 30 }, (_, i) => ({
  left: `${(i * 37 + 8) % 100}%`,
  dur: `${0.52 + (i % 5) * 0.12}s`,
  delay: `${(i * 0.18) % 2.2}s`,
  h: `${12 + (i % 8) * 3}px`,
  op: 0.1 + (i % 7) * 0.035,
}));

const PARTICLES = Array.from({ length: 18 }, (_, i) => ({
  left: `${(i * 47 + 13) % 90}%`,
  bottom: `${14 + (i * 31) % 48}%`,
  dur: `${2.2 + (i % 4) * 0.45}s`,
  delay: `${(i * 0.29) % 3.2}s`,
  sz: 2 + (i % 3),
}));

const KEYFRAMES = `
  @keyframes cloudDrift {
    from { transform: translateX(-440px); }
    to { transform: translateX(980px); }
  }
  @keyframes rainFall {
    0% { transform: translateY(-22px); opacity: 0; }
    14% { opacity: 1; }
    84% { opacity: 1; }
    100% { transform: translateY(440px); opacity: 0; }
  }
  @keyframes particleFloat {
    0% { transform: translate(0, 0); opacity: 0; }
    20% { opacity: 0.9; }
    100% { transform: translate(10px, -82px); opacity: 0; }
  }
  @keyframes sunPulse {
    0%, 100% { transform: scale(1); }
    50% { transform: scale(1.055); }
  }
  @keyframes statusPulse {
    0%, 100% { opacity: 0.5; box-shadow: 0 0 15px rgba(255,194,10,0.3); }
    50% { opacity: 1; box-shadow: 0 0 24px rgba(255,194,10,0.74); }
  }
`;

function mkTrend(peak: number) {
  return TREND_WEIGHTS.map((weight, index) => ({ h: `${index + 5}h`, v: Math.round(weight * peak) }));
}

function CloudBlob({ w, blur }: { w: number; blur: number }) {
  const h = Math.round(w * 0.33);

  return (
    <div style={{ width: w, height: h, position: "relative", filter: `blur(${blur}px)` }}>
      <div style={{ position: "absolute", width: "54%", height: "100%", left: "22%", background: "rgba(235,243,250,0.74)", borderRadius: "50%" }} />
      <div style={{ position: "absolute", width: "38%", height: "78%", left: "3%", top: "18%", background: "rgba(235,243,250,0.58)", borderRadius: "50%" }} />
      <div style={{ position: "absolute", width: "33%", height: "75%", right: "5%", top: "13%", background: "rgba(235,243,250,0.62)", borderRadius: "50%" }} />
    </div>
  );
}

function SkyCanvas({ level }: { level: Level }) {
  const cfg = CFG[level];

  return (
    <div className="absolute inset-0 overflow-hidden" style={{ borderRadius: "0 28px 28px 0" }}>
      <div
        className="absolute inset-0 transition-all duration-[2600ms] ease-in-out"
        style={{ background: `linear-gradient(145deg, ${cfg.skyFrom} 0%, ${cfg.skyMid} 55%, ${cfg.skyTo} 100%)` }}
      />
      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_30%_35%,rgba(239,246,255,0.4)_0%,rgba(239,246,255,0.16)_25%,transparent_54%)]" />
      <div className="absolute inset-0 bg-[linear-gradient(90deg,rgba(5,10,16,0.14)_0%,transparent_42%,rgba(5,10,16,0.28)_100%)]" />

      <div className="absolute right-[15%] top-[15%] transition-opacity duration-[2600ms]" style={{ opacity: cfg.sunOpacity }}>
        <div className="absolute left-1/2 top-1/2 h-[122px] w-[122px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-[radial-gradient(circle,rgba(255,207,78,0.24)_0%,transparent_70%)] blur-[6px]" />
        <div
          className="relative h-[60px] w-[60px] rounded-full bg-[radial-gradient(circle_at_36%_31%,#fff7cf_0%,#ffd45d_36%,#c7891b_100%)]"
          style={{ boxShadow: "0 0 58px rgba(255,194,10,0.36)", animation: "sunPulse 5s ease-in-out infinite" }}
        />
      </div>

      {CLOUDS.map((cloud, index) => (
        <div
          key={index}
          className="absolute"
          style={{
            top: cloud.top,
            opacity: cfg.cloudOpacity * cloud.baseOp,
            transition: "opacity 2.5s ease",
            animation: `cloudDrift ${cloud.dur} linear ${cloud.delay} infinite`,
          }}
        >
          <CloudBlob w={cloud.w} blur={cloud.blur} />
        </div>
      ))}

      {cfg.rainVisible &&
        RAIN.map((drop, index) => (
          <div
            key={index}
            className="absolute"
            style={{
              left: drop.left,
              top: -18,
              width: 1,
              height: drop.h,
              background: "rgba(190,220,247,0.52)",
              opacity: drop.op,
              animation: `rainFall ${drop.dur} linear ${drop.delay} infinite`,
            }}
          />
        ))}

      {cfg.particles &&
        PARTICLES.map((particle, index) => (
          <div
            key={index}
            className="absolute rounded-full"
            style={{
              left: particle.left,
              bottom: particle.bottom,
              width: particle.sz,
              height: particle.sz,
              background: "rgba(255,238,151,0.92)",
              boxShadow: `0 0 ${particle.sz * 4}px rgba(255,210,68,0.72)`,
              animation: `particleFloat ${particle.dur} ease-out ${particle.delay} infinite`,
            }}
          />
        ))}

      <div className="absolute inset-0 bg-[radial-gradient(ellipse_at_center,transparent_48%,rgba(0,5,10,0.34)_100%)]" />
    </div>
  );
}

function ControlPill({
  active,
  children,
  color,
  onClick,
}: {
  active: boolean;
  children: string;
  color: string;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className="h-7 rounded-full px-4 text-[11px] font-semibold tracking-[0.08em] transition-all duration-300"
      style={{
        fontFamily: "'JetBrains Mono', monospace",
        background: active ? `${color}12` : "rgba(255,255,255,0.045)",
        border: `1px solid ${active ? `${color}80` : "rgba(255,255,255,0.12)"}`,
        color: active ? color : "rgba(190,202,216,0.48)",
        boxShadow: active ? `0 0 18px ${color}18 inset` : "none",
      }}
    >
      {children}
    </button>
  );
}

const CustomTooltip = ({ active, payload }: { active?: boolean; payload?: Array<{ value: number }> }) => {
  if (!active || !payload?.length) {
    return null;
  }

  return (
    <div className="rounded-md border border-white/10 bg-[#0a0f16]/90 px-2.5 py-1.5 backdrop-blur">
      <span className="text-[11px] text-white/80" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
        {payload[0].value} W/m2
      </span>
    </div>
  );
};

export default function App() {
  const [level, setLevel] = useState<Level>("medium");
  const [auto, setAuto] = useState(true);
  const [liveRad, setLiveRad] = useState(CFG.medium.radiation);

  const cfg = CFG[level];
  const trend = useMemo(() => mkTrend(cfg.trendPeak), [cfg.trendPeak]);

  useEffect(() => {
    if (!auto) {
      return;
    }

    const cycle: Level[] = ["high", "medium", "low"];
    const id = setInterval(() => {
      setLevel((prev) => cycle[(cycle.indexOf(prev) + 1) % cycle.length]);
    }, 9000);

    return () => clearInterval(id);
  }, [auto]);

  useEffect(() => {
    const base = cfg.radiation;
    setLiveRad(base);

    const id = setInterval(() => {
      setLiveRad(Math.round(base + (Math.random() - 0.5) * base * 0.045));
    }, 2600);

    return () => clearInterval(id);
  }, [cfg.radiation]);

  function handleSelect(nextLevel: Level) {
    setLevel(nextLevel);
    setAuto(false);
  }

  return (
    <div
      className="min-h-screen overflow-hidden px-7 py-5 md:px-8"
      style={{
        fontFamily: "'Outfit', sans-serif",
        background:
          "radial-gradient(circle at 80% 0%, rgba(19,47,69,0.2) 0%, transparent 32%), linear-gradient(180deg, #03080d 0%, #010408 100%)",
      }}
    >
      <style>{KEYFRAMES}</style>

      <main className="mx-auto flex min-h-[calc(100vh-40px)] w-full max-w-[1150px] flex-col justify-center">
        <header className="mb-6 flex flex-wrap items-start justify-between gap-4">
          <div>
            <p className="text-[11px] uppercase tracking-[0.42em] text-slate-500" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
              Condicion solar en tiempo real
            </p>
            <p className="mt-3 text-[13px] text-slate-600" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
              25 May 2026 - 19:14 COT - 24 paneles - 13.2 kWp
            </p>
          </div>

          <div className="flex gap-2">
            {(["high", "medium", "low"] as Level[]).map((item) => (
              <ControlPill key={item} active={item === level} color={CFG[item].color} onClick={() => handleSelect(item)}>
                {item === "high" ? "Alta" : item === "medium" ? "Media" : "Baja"}
              </ControlPill>
            ))}
            <ControlPill active={auto} color="#9387ff" onClick={() => setAuto((current) => !current)}>
              Auto
            </ControlPill>
          </div>
        </header>

        <section
          className="grid min-h-[375px] overflow-hidden rounded-[28px] md:grid-cols-[42%_58%]"
          style={{
            background: "linear-gradient(180deg, rgba(9,17,27,0.96) 0%, rgba(5,12,20,0.98) 100%)",
            border: "1px solid rgba(113,132,154,0.18)",
            boxShadow: "0 28px 90px rgba(0,0,0,0.44)",
          }}
        >
          <aside className="relative z-10 border-b border-white/[0.06] p-9 md:border-b-0 md:border-r">
            <div className="flex items-start gap-5">
              <div className="flex flex-col items-center gap-2 pt-1">
                <span className="h-3.5 w-3.5 rounded-full bg-slate-700" />
                <span
                  className="h-4 w-4 rounded-full"
                  style={{ background: cfg.color, animation: "statusPulse 2.4s ease-in-out infinite" }}
                />
                <span className="h-3.5 w-3.5 rounded-full bg-slate-700" />
              </div>

              <div>
                <motion.h1
                  key={`title-${level}`}
                  initial={{ opacity: 0, y: 6 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.4 }}
                  className="text-[16px] font-semibold"
                  style={{ color: cfg.color }}
                >
                  {cfg.label}
                </motion.h1>
                <p className="mt-2 text-[13px] font-medium text-slate-500">{cfg.sublabel}</p>
              </div>
            </div>

            <div className="mt-8">
              <p className="text-[11px] uppercase tracking-[0.34em] text-slate-600" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                Irradiancia solar
              </p>
              <div className="mt-4 flex items-end gap-3">
                <motion.span
                  key={`rad-${level}`}
                  initial={{ opacity: 0, y: 10 }}
                  animate={{ opacity: 1, y: 0 }}
                  transition={{ duration: 0.45 }}
                  className="text-[58px] font-light leading-none text-slate-100 tabular-nums"
                  style={{ fontFamily: "'JetBrains Mono', monospace" }}
                >
                  {liveRad}
                </motion.span>
                <span className="mb-3 text-[14px] text-slate-600" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                  W/m2
                </span>
              </div>
            </div>

            <div className="mt-9 grid grid-cols-3 gap-4 border-y border-white/[0.06] py-5">
              <div>
                <p className="text-[10px] uppercase tracking-[0.25em] text-slate-600" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                  Produccion
                </p>
                <p className="mt-3 text-[22px] text-slate-100">
                  {cfg.production}
                  <span className="ml-1 text-[11px] text-slate-500">kW</span>
                </p>
              </div>
              <div>
                <p className="text-[10px] uppercase tracking-[0.25em] text-slate-600" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                  Nubosidad
                </p>
                <p className="mt-3 text-[22px] text-slate-100">
                  {cfg.cloudiness}
                  <span className="ml-1 text-[11px] text-slate-500">%</span>
                </p>
              </div>
              <div>
                <p className="text-[10px] uppercase tracking-[0.25em] text-slate-600" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                  Estado
                </p>
                <p className="mt-3 flex items-center gap-1.5 text-[12px] leading-4 text-slate-300">
                  <Zap size={13} style={{ color: cfg.color }} />
                  {cfg.statusText}
                </p>
              </div>
            </div>

            <div className="mt-8">
              <div className="mb-3 flex items-center justify-between">
                <span className="text-[11px] uppercase tracking-[0.3em] text-slate-600" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                  Eficiencia del sistema
                </span>
                <span className="text-[12px] text-slate-400" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                  {cfg.efficiency}%
                </span>
              </div>
              <div className="h-[4px] overflow-hidden rounded-full bg-slate-800">
                <div
                  className="h-full rounded-full transition-all duration-[2200ms]"
                  style={{ width: `${cfg.efficiency}%`, background: `linear-gradient(90deg, ${cfg.color}55, ${cfg.color})` }}
                />
              </div>
            </div>
          </aside>

          <section className="relative min-h-[375px] p-7">
            <SkyCanvas level={level} />

            <div className="relative z-10 flex h-full flex-col justify-between">
              <div className="inline-flex w-fit items-center gap-2 rounded-full border border-white/[0.08] bg-black/28 px-3.5 py-2 text-[11px] font-semibold tracking-[0.08em] text-slate-300 shadow-[0_10px_26px_rgba(0,0,0,0.2)] backdrop-blur">
                <span className="h-2 w-2 rounded-full" style={{ background: cfg.color, boxShadow: `0 0 12px ${cfg.color}` }} />
                {cfg.skyTag}
              </div>

              <div className="mt-auto">
                <div className="mb-6 flex items-center gap-2 text-[11px] uppercase tracking-[0.28em] text-slate-400" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
                  <TrendingUp size={13} />
                  Tendencia solar - 6h - 18h
                </div>

                <ResponsiveContainer width="100%" height={86}>
                  <AreaChart data={trend} margin={{ top: 4, right: 0, bottom: 0, left: 0 }}>
                    <defs>
                      <linearGradient id="chartGrad" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor={cfg.chartColor} stopOpacity={0.32} />
                        <stop offset="100%" stopColor={cfg.chartColor} stopOpacity={0} />
                      </linearGradient>
                    </defs>
                    <Area
                      type="monotone"
                      dataKey="v"
                      stroke={cfg.chartColor}
                      strokeWidth={2}
                      fill="url(#chartGrad)"
                      dot={false}
                      isAnimationActive
                      animationDuration={900}
                    />
                    <Tooltip content={<CustomTooltip />} />
                  </AreaChart>
                </ResponsiveContainer>
              </div>
            </div>
          </section>
        </section>

        <footer className="mt-5 flex flex-wrap items-center justify-between gap-3 text-[11px] tracking-[0.12em] text-slate-700" style={{ fontFamily: "'JetBrains Mono', monospace" }}>
          <span className="inline-flex items-center gap-2">
            <RadioTower size={13} />
            Actualizacion - cada 30 s
          </span>
          <span>Solar Dashboard v2.4 - 2026</span>
        </footer>
      </main>
    </div>
  );
}
