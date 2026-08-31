document.querySelectorAll('[data-copy]').forEach(btn=>btn.addEventListener('click',async()=>{try{await navigator.clipboard.writeText(btn.dataset.copy);const old=btn.textContent;btn.textContent='Copied!';setTimeout(()=>btn.textContent=old,1600)}catch(e){prompt('Copy this invitation link:',btn.dataset.copy)}}));

