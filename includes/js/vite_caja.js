// Entry específico para el módulo CAJA
// // Aquí inicializamos Alpine sólo para esta página
// import Alpine from "alpinejs";

// // Importa persist si lo necesitas en este módulo
// // import persist from '@alpinejs/persist'
// // Alpine.plugin(persist)

// window.Alpine = Alpine;
// Alpine.start();

export function updateSubtotal(inputId, moneda = "GTQ") {
  const input = document.getElementById(inputId);
  const den = parseFloat(input.dataset.den);
  const qty = parseInt(input.value) || 0;
  const subtotal = den * qty;
  document.getElementById("s" + inputId).textContent =
    moneda + " " + subtotal.toFixed(2);
  updateTotal(moneda);
}

export function updateTotal(moneda = "GTQ") {
  let total = 0;
  document.querySelectorAll(".den-input").forEach((inp) => {
    const den = parseFloat(inp.dataset.den);
    const qty = parseInt(inp.value) || 0;
    total += den * qty;
  });
  document.getElementById("totalGeneral").textContent =
    moneda + " " + total.toFixed(2);
  document.getElementById("montoTotal").value = total.toFixed(2);
}

if (typeof window !== 'undefined') {
  window.updateSubtotal = updateSubtotal;
  window.updateTotal = updateTotal;
}

// export default Alpine;
