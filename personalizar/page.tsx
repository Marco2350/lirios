import { PersonalizerClient } from "./personalizer-client";

export const metadata = {
  title: "Personaliza tu ramo · Lirios",
  description:
    "Diseña tu ramo flor por flor. Elige envoltura, listón y dedicatoria.",
};

export default function PersonalizarPage() {
  return <PersonalizerClient />;
}
