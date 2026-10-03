const envelopeButton = document.getElementById("envelopeButton");
const envelopeScene = document.getElementById("envelopeScene");
const opening = document.getElementById("opening");
const invitation = document.getElementById("invitation");
const backToTop = document.getElementById("backToTop");

let opened = false;

function openInvitation() {
  if (opened) return;
  opened = true;

  // 1. Amplop terbuka, menampilkan kertas putih dari dalam.
  envelopeScene.classList.add("opening-envelope");

  // 2. Halaman invitation yang berada tepat di atas kertas putih
  //    melompat keluar dari mulut amplop.
  setTimeout(() => {
    envelopeScene.classList.add("paper-emerging");
  }, 420);

  // 3. Kertas yang sama terbang mendekati layar dan memenuhi viewport.
  setTimeout(() => {
    envelopeScene.classList.add("paper-fullscreen");
  }, 1180);

  // 4. Siapkan halaman setelah cover: invitation lalu info.
  setTimeout(() => {
    invitation.classList.add("visible");
  }, 1450);

  // 5. Hilangkan layer pembuka setelah transisi selesai.
  setTimeout(() => {
    opening.classList.add("is-hidden");
    window.scrollTo({ top: 0, behavior: "auto" });
  }, 2050);
}

envelopeButton.addEventListener("click", openInvitation);

envelopeButton.addEventListener("keydown", (event) => {
  if (event.key === "Enter" || event.key === " ") {
    event.preventDefault();
    openInvitation();
  }
});

backToTop.addEventListener("click", () => {
  window.scrollTo({ top: 0, behavior: "smooth" });
});
