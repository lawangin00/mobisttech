export type CartLine = {
  product_id: string;
  slug: string;
  name: string;
  price: string;
  quantity: number;
};

const KEY = "mobisttech.customer.cart.v1";

function valid(value: unknown): value is CartLine[] {
  return Array.isArray(value) && value.every((line) => {
    if (typeof line !== "object" || line === null) return false;
    const item = line as Partial<CartLine>;
    return typeof item.product_id === "string"
      && typeof item.slug === "string"
      && typeof item.name === "string"
      && typeof item.price === "string"
      && Number.isInteger(item.quantity)
      && Number(item.quantity) > 0
      && Number(item.quantity) <= 99;
  });
}

export function readCart(): CartLine[] {
  if (typeof window === "undefined") return [];
  try {
    const parsed: unknown = JSON.parse(window.localStorage.getItem(KEY) ?? "[]");
    return valid(parsed) ? parsed : [];
  } catch {
    return [];
  }
}

export function writeCart(lines: CartLine[]) {
  if (typeof window === "undefined") return;
  window.localStorage.setItem(KEY, JSON.stringify(lines));
  window.dispatchEvent(new Event("mobisttech-cart-change"));
}

export function clearCart() {
  writeCart([]);
}
