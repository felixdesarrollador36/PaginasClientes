(() => {
  const container = document.getElementById('debris');
  const backLink = document.querySelector('[data-history-back]');

  if (backLink) {
    backLink.addEventListener('click', (event) => {
      event.preventDefault();
      window.history.back();
    });
  }

  if (!container) {
    return;
  }

  const colors = ['#6366f1', '#7c3aed', '#22d3ee', '#f43f5e', '#f59e0b'];
  const count = 22;

  for (let index = 0; index < count; index += 1) {
    const shard = document.createElement('div');
    const color = colors[Math.floor(Math.random() * colors.length)];
    const width = 1 + Math.random() * 2;
    const height = 20 + Math.random() * 80;

    shard.className = 'shard';
    shard.style.setProperty('--w', `${width}px`);
    shard.style.setProperty('--h', `${height}px`);
    shard.style.setProperty('--c', color);
    shard.style.setProperty('--op', `${0.05 + Math.random() * 0.2}`);
    shard.style.setProperty('--dur', `${6 + Math.random() * 10}s`);
    shard.style.setProperty('--delay', `-${Math.random() * 10}s`);
    shard.style.setProperty('--rot', `${-30 + Math.random() * 60}deg`);
    shard.style.setProperty('--left', `${Math.random() * 100}%`);
    container.appendChild(shard);
  }
})();
