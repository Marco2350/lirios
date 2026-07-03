"use client";

import { useMemo, useState } from "react";
import { flowers, wraps, ribbons, formatPrice } from "@/lib/data";
import { Flower } from "@/components/flowers/Flower";
import { InteractiveBouquetView } from "@/components/flowers/InteractiveBouquetView";
import { Button } from "@/components/ui/button";
import { Textarea } from "@/components/ui/textarea";
import { Label } from "@/components/ui/label";
import {
  Tabs,
  TabsContent,
  TabsList,
  TabsTrigger,
} from "@/components/ui/tabs";
import {
  Minus,
  Plus,
  ShoppingBag,
  Sparkles,
  Trash2,
  Shuffle,
  Hand,
} from "lucide-react";
import { useCart, calculateCustomPrice } from "@/lib/cart-store";
import {
  addPlacement,
  removePlacement,
  autoLayout,
  countByFlower,
  totalStems,
  type Placement,
} from "@/lib/bouquet-layout";
import { toast } from "sonner";
import { cn } from "@/lib/utils";

const MAX_STEMS = 36;

export function PersonalizerClient() {
  const [placements, setPlacements] = useState<Placement[]>([]);
  const [wrapId, setWrapId] = useState(wraps[1].id);
  const [ribbonId, setRibbonId] = useState(ribbons[0].id);
  const [message, setMessage] = useState("");
  const addCustom = useCart((s) => s.addCustom);

  const counts = useMemo(() => countByFlower(placements), [placements]);
  const total = totalStems(placements);

  const stemsArray = useMemo(
    () =>
      Object.entries(counts).map(([flowerId, qty]) => ({ flowerId, qty })),
    [counts],
  );

  const price = calculateCustomPrice({
    flowers: stemsArray,
    wrapId,
    ribbonId,
    cardMessage: message,
  });

  function add(flowerId: string) {
    if (total >= MAX_STEMS) {
      toast.warning(`Máximo ${MAX_STEMS} flores por ramo`);
      return;
    }
    setPlacements((p) => addPlacement(p, flowerId));
  }
  function remove(flowerId: string) {
    setPlacements((p) => removePlacement(p, flowerId));
  }

  function reset() {
    setPlacements([]);
    setMessage("");
  }

  function reshuffle() {
    setPlacements(autoLayout(stemsArray));
    toast.success("Reorganizado");
  }

  function handleAdd() {
    if (total === 0) {
      toast.error("Agrega al menos una flor a tu ramo.");
      return;
    }
    addCustom(
      {
        flowers: stemsArray,
        wrapId,
        ribbonId,
        cardMessage: message,
      },
      price,
    );
    toast.success("Tu ramo personalizado se agregó a la canasta.");
    reset();
  }

  const hasPinned = placements.some((p) => p.pinned);

  return (
    <div className="mx-auto w-full max-w-7xl px-4 py-10 sm:px-6 md:py-16 lg:px-8">
      <div className="flex flex-col gap-3">
        <p className="font-display text-sm uppercase tracking-[0.3em] text-primary">
          Tu ramo, tus reglas
        </p>
        <h1 className="font-display text-4xl tracking-tight sm:text-5xl md:text-6xl">
          Diseña tu ramo flor por flor.
        </h1>
        <p className="max-w-2xl text-base text-muted-foreground">
          Elige las flores, arrástralas para reacomodarlas a tu gusto y decide
          el papel y el listón. Lo armamos a mano el día de tu entrega.
        </p>
      </div>

      <div className="mt-10 grid gap-10 lg:grid-cols-5">
        {/* PREVIEW */}
        <div className="lg:col-span-3 lg:sticky lg:top-24 lg:self-start">
          <div className="relative overflow-hidden rounded-3xl border border-border/60 bg-card">
            {/* Studio background — soft warm bokeh with paper grain */}
            <div
              className="absolute inset-0"
              style={{
                background:
                  "radial-gradient(120% 90% at 50% 25%, oklch(0.97 0.018 85) 0%, oklch(0.93 0.025 80) 45%, oklch(0.88 0.03 75) 100%)",
              }}
            />
            {/* Soft out-of-focus highlights */}
            <div
              className="pointer-events-none absolute inset-0"
              style={{
                background:
                  "radial-gradient(circle at 25% 28%, oklch(1 0 0 / 0.55) 0px, transparent 16%)," +
                  "radial-gradient(circle at 78% 22%, oklch(1 0 0 / 0.4) 0px, transparent 14%)," +
                  "radial-gradient(circle at 85% 78%, oklch(0.85 0.05 25 / 0.35) 0px, transparent 18%)," +
                  "radial-gradient(circle at 18% 80%, oklch(0.86 0.05 130 / 0.3) 0px, transparent 20%)",
                filter: "blur(1px)",
              }}
            />
            {/* Paper grain */}
            <div
              className="pointer-events-none absolute inset-0 opacity-[0.08] mix-blend-multiply"
              style={{
                backgroundImage:
                  "url(\"data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.85' numOctaves='2' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)'/%3E%3C/svg%3E\")",
              }}
            />
            {/* Vignette */}
            <div
              className="pointer-events-none absolute inset-0"
              style={{
                background:
                  "radial-gradient(ellipse 80% 80% at 50% 50%, transparent 50%, oklch(0.2 0.03 60 / 0.18) 100%)",
              }}
            />

            <div className="relative aspect-square flex items-center justify-center p-6">
              <InteractiveBouquetView
                placements={placements}
                wrapId={wrapId}
                ribbonId={ribbonId}
                size={520}
                interactive
                onPlacementsChange={setPlacements}
                className="drop-shadow-2xl"
              />
            </div>

            {/* Top-right controls */}
            <div className="absolute right-4 top-4 flex gap-2">
              {hasPinned && (
                <button
                  onClick={reshuffle}
                  className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-background/85 px-3 py-1.5 text-xs text-muted-foreground backdrop-blur transition hover:text-foreground"
                  title="Reorganizar automáticamente"
                >
                  <Shuffle className="size-3" />
                  Reorganizar
                </button>
              )}
              {placements.length > 0 && (
                <button
                  onClick={reset}
                  className="inline-flex items-center gap-1.5 rounded-full border border-border/60 bg-background/85 px-3 py-1.5 text-xs text-muted-foreground backdrop-blur transition hover:text-foreground"
                >
                  <Trash2 className="size-3" />
                  Reiniciar
                </button>
              )}
            </div>

            {/* Hint */}
            {placements.length > 0 && (
              <div className="pointer-events-none absolute bottom-4 left-1/2 z-10 -translate-x-1/2">
                <div className="inline-flex items-center gap-1.5 rounded-full bg-background/90 px-3 py-1.5 text-[11px] text-muted-foreground shadow-sm backdrop-blur">
                  <Hand className="size-3" />
                  Arrastra para mover · rueda del mouse para rotar
                </div>
              </div>
            )}
          </div>

          {/* Summary */}
          <div className="mt-5 grid gap-4 rounded-2xl border border-border/60 bg-card p-5">
            <div className="flex items-baseline justify-between">
              <div>
                <p className="text-xs uppercase tracking-widest text-muted-foreground">
                  Tu ramo
                </p>
                <p className="mt-1 font-display text-2xl tracking-tight">
                  {total === 0
                    ? "Vacío"
                    : `${total} ${total === 1 ? "flor" : "flores"}`}
                </p>
              </div>
              <div className="text-right">
                <p className="text-xs uppercase tracking-widest text-muted-foreground">
                  Precio estimado
                </p>
                <p className="mt-1 font-display text-3xl tracking-tight tabular-nums">
                  {formatPrice(price)}
                </p>
              </div>
            </div>

            {stemsArray.length > 0 && (
              <div className="flex flex-wrap gap-1.5">
                {stemsArray.map((s) => {
                  const f = flowers.find((x) => x.id === s.flowerId);
                  if (!f) return null;
                  return (
                    <span
                      key={s.flowerId}
                      className="inline-flex items-center gap-1.5 rounded-full border border-border bg-background px-2.5 py-1 text-xs"
                    >
                      <span
                        className="inline-block size-2 rounded-full"
                        style={{ background: f.color }}
                      />
                      {f.name} × {s.qty}
                    </span>
                  );
                })}
              </div>
            )}

            <Button
              size="lg"
              className="w-full rounded-full"
              onClick={handleAdd}
              disabled={total === 0}
            >
              <ShoppingBag className="mr-1.5 size-4" />
              Agregar a la canasta — {formatPrice(price)}
            </Button>
            <p className="text-center text-[11px] text-muted-foreground">
              <Sparkles className="mr-1 inline size-3" />
              Tu ramo se arma a mano. Puede variar mínimamente del diseño.
            </p>
          </div>
        </div>

        {/* CONTROLS */}
        <div className="lg:col-span-2">
          <Tabs defaultValue="flores" className="w-full">
            <TabsList className="grid w-full grid-cols-4 rounded-full bg-muted">
              <TabsTrigger value="flores" className="rounded-full">
                Flores
              </TabsTrigger>
              <TabsTrigger value="envoltura" className="rounded-full">
                Papel
              </TabsTrigger>
              <TabsTrigger value="liston" className="rounded-full">
                Listón
              </TabsTrigger>
              <TabsTrigger value="mensaje" className="rounded-full">
                Mensaje
              </TabsTrigger>
            </TabsList>

            <TabsContent value="flores" className="mt-6">
              <p className="mb-4 text-sm text-muted-foreground">
                Elige tus flores. Cada tallo es independiente — combínalas como
                quieras.
              </p>
              <div className="grid grid-cols-2 gap-3">
                {flowers.map((f) => {
                  const qty = counts[f.id] ?? 0;
                  return (
                    <div
                      key={f.id}
                      className={cn(
                        "flex flex-col overflow-hidden rounded-xl border bg-card transition",
                        qty > 0
                          ? "border-foreground/40 shadow-sm"
                          : "border-border/60",
                      )}
                    >
                      <div
                        className="flex aspect-square items-center justify-center"
                        style={{
                          background: `linear-gradient(135deg, ${f.color}15 0%, ${f.accentColor ?? "#fff"}10 100%)`,
                        }}
                      >
                        <Flower
                          kind={f.kind}
                          color={f.color}
                          accentColor={f.accentColor}
                          size={96}
                        />
                      </div>
                      <div className="flex flex-col gap-2 p-3">
                        <div>
                          <p className="text-sm font-medium leading-tight">
                            {f.name}
                          </p>
                          <p className="text-[11px] text-muted-foreground tabular-nums">
                            {formatPrice(f.pricePerStem)} / tallo
                          </p>
                        </div>
                        <div className="flex items-center justify-between gap-2">
                          {qty === 0 ? (
                            <Button
                              size="sm"
                              variant="outline"
                              className="h-8 w-full rounded-full text-xs"
                              onClick={() => add(f.id)}
                            >
                              <Plus className="mr-1 size-3" />
                              Agregar
                            </Button>
                          ) : (
                            <div className="flex w-full items-center justify-between rounded-full border border-border">
                              <button
                                onClick={() => remove(f.id)}
                                className="px-3 py-1.5 text-muted-foreground hover:text-foreground"
                                aria-label="Restar"
                              >
                                <Minus className="size-3" />
                              </button>
                              <span className="text-sm font-medium tabular-nums">
                                {qty}
                              </span>
                              <button
                                onClick={() => add(f.id)}
                                className="px-3 py-1.5 text-muted-foreground hover:text-foreground"
                                aria-label="Sumar"
                              >
                                <Plus className="size-3" />
                              </button>
                            </div>
                          )}
                        </div>
                      </div>
                    </div>
                  );
                })}
              </div>
            </TabsContent>

            <TabsContent value="envoltura" className="mt-6">
              <p className="mb-4 text-sm text-muted-foreground">
                ¿Cómo lo envolvemos?
              </p>
              <div className="grid gap-3">
                {wraps.map((w) => {
                  const selected = wrapId === w.id;
                  return (
                    <button
                      key={w.id}
                      onClick={() => setWrapId(w.id)}
                      className={cn(
                        "flex items-center gap-4 rounded-xl border bg-card p-4 text-left transition",
                        selected
                          ? "border-foreground/40 shadow-sm"
                          : "border-border/60 hover:border-foreground/20",
                      )}
                    >
                      <div
                        className="size-14 shrink-0 rounded-lg border border-border/40"
                        style={{ background: w.color }}
                      />
                      <div className="flex-1">
                        <p className="font-medium">{w.name}</p>
                        <p className="text-xs text-muted-foreground">
                          {w.description}
                        </p>
                      </div>
                      <span className="text-sm tabular-nums">
                        {formatPrice(w.price)}
                      </span>
                    </button>
                  );
                })}
              </div>
            </TabsContent>

            <TabsContent value="liston" className="mt-6">
              <p className="mb-4 text-sm text-muted-foreground">
                Elige el color del listón.
              </p>
              <div className="grid grid-cols-3 gap-3 sm:grid-cols-6">
                {ribbons.map((r) => {
                  const selected = ribbonId === r.id;
                  return (
                    <button
                      key={r.id}
                      onClick={() => setRibbonId(r.id)}
                      className={cn(
                        "flex flex-col items-center gap-2 rounded-xl border bg-card p-3 transition",
                        selected
                          ? "border-foreground/40 ring-2 ring-foreground/10"
                          : "border-border/60 hover:border-foreground/20",
                      )}
                    >
                      <span
                        className="size-10 rounded-full border border-border/40 shadow-inner"
                        style={{ background: r.color }}
                      />
                      <span className="text-xs">{r.name}</span>
                    </button>
                  );
                })}
              </div>
            </TabsContent>

            <TabsContent value="mensaje" className="mt-6">
              <Label htmlFor="message">Tarjeta dedicatoria (opcional)</Label>
              <p className="mt-1 text-xs text-muted-foreground">
                La escribimos a mano en cartulina de algodón. Máximo 240
                caracteres.
              </p>
              <Textarea
                id="message"
                value={message}
                onChange={(e) => setMessage(e.target.value.slice(0, 240))}
                placeholder="Para ti, porque sí. Con todo mi cariño, …"
                rows={6}
                className="mt-3 resize-none"
              />
              <div className="mt-2 flex justify-between text-xs text-muted-foreground">
                <span>Incluye nombre del remitente si quieres.</span>
                <span className="tabular-nums">{message.length}/240</span>
              </div>

              {message && (
                <div className="mt-6 rounded-xl border border-border/60 bg-card p-6">
                  <p className="text-xs uppercase tracking-widest text-muted-foreground">
                    Vista previa
                  </p>
                  <p className="mt-3 whitespace-pre-wrap font-display text-lg italic leading-relaxed">
                    “{message}”
                  </p>
                </div>
              )}
            </TabsContent>
          </Tabs>
        </div>
      </div>
    </div>
  );
}
