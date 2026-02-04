<script>
async function configureForm(org, form, type, name) {
    const btn = event.target;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analyse...';
    
    try {
        const response = await fetch(`admin.php?action=analyze&org=${org}&form=${form}&type=${type}`);
        const data = await response.json();
        const items = data.rules || data; // Gère les deux formats possibles
        
        let html = `
            <div id="editor-container" class="mt-8 p-10 bg-slate-900 rounded-[2rem] border border-blue-500/30 animate-fade-in shadow-2xl">
                <div class="mb-8">
                    <h3 class="text-xl font-black text-white">Nouveau Board : ${name}</h3>
                    <p class="text-slate-500 text-sm">Définissez les réglages par défaut pour ce formulaire.</p>
                </div>

                <table class="w-full text-xs text-left">
                    <thead>
                        <tr class="text-slate-500 uppercase tracking-widest text-[10px] font-black border-b border-slate-800">
                            <th class="pb-4 px-2">Visible</th>
                            <th class="pb-4 px-2">Item HelloAsso</th>
                            <th class="pb-4 px-2">Nom Affiché</th>
                            <th class="pb-4 px-2">Type</th>
                            <th class="pb-4 px-2">Bloc</th>
                        </tr>
                    </thead>
                    <tbody id="rules-body">
        `;

        const ruleList = Array.isArray(items) ? items : items.rules || [];

        ruleList.forEach((item, i) => {
            const pattern = typeof item === 'string' ? item : item.pattern;
            html += `
                <tr class="border-b border-slate-800/50 rule-row group hover:bg-white/5" data-pattern="${pattern}">
                    <td class="py-4 px-2"><input type="checkbox" class="rule-visible w-4 h-4 accent-emerald-500" checked></td>
                    <td class="py-4 px-2 font-mono text-slate-500 max-w-[150px] truncate">${pattern}</td>
                    <td class="py-4 px-2"><input type="text" class="display-label w-full bg-slate-800 border border-slate-700 rounded px-2 py-1.5" value="${pattern}"></td>
                    <td class="py-4 px-2">
                        <select class="rule-type bg-slate-800 border border-slate-700 rounded px-1 py-1.5 w-full">
                            <option value="Billet">🎫 Billet</option>
                            <option value="Option" selected>📊 Option</option>
                            <option value="Ignorer">🚫 Ignorer</option>
                        </select>
                    </td>
                    <td class="py-4 px-2"><input type="text" class="rule-group w-full bg-slate-800 border border-slate-700 rounded px-2 py-1.5" placeholder="Ex: Repas"></td>
                </tr>
            `;
        });

        html += `</tbody></table>
                <div class="mt-10 flex justify-end gap-4">
                     <button onclick="location.reload()" class="px-6 py-3 text-slate-500 font-bold">Annuler</button>
                    <button onclick="saveFullCampaign('${org}','${form}','${type}','${name.replace(/'/g, "\\'")}')" class="bg-emerald-600 hover:bg-emerald-500 px-10 py-4 rounded-2xl font-black text-white shadow-xl transition transform hover:scale-105">
                        <i class="fa-solid fa-rocket mr-2"></i> Lancer le Board
                    </button>
                </div>
            </div>`;
        
        const container = document.querySelector('.bg-slate-800\\/50') || document.getElementById('config-zone');
        container.innerHTML = html;
        container.scrollIntoView({ behavior: 'smooth' });
    } catch (e) {
        console.error(e);
        alert("Erreur lors de l'analyse.");
        btn.innerHTML = 'Configurer';
    }
}

async function saveFullCampaign(org, form, type, name) {
    const rules = [];
    document.querySelectorAll('.rule-row').forEach(row => {
        rules.push({
            pattern: row.dataset.pattern,
            displayLabel: row.querySelector('.display-label').value,
            type: row.querySelector('.rule-type').value,
            group: row.querySelector('.rule-group').value || 'Divers',
            chartType: "doughnut",
            transform: "",
            hidden: !row.querySelector('.rule-visible').checked
        });
    });

    const config = {
        slug: form,
        title: name,
        orgSlug: org,
        formSlug: form,
        formType: type,
        icon: "mask",
        rules: rules,
        goals: { revenue: 0, n1: 0 }
    };

    const body = new URLSearchParams();
    body.append('save_campaign', '1');
    body.append('config', JSON.stringify(config));

    const res = await fetch('admin.php', { method: 'POST', body: body });
    window.location.href = 'index.php?campaign=' + form;
}
</script>