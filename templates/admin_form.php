<script>
async function configureForm(org, form, type, name) {
    const btn = event.currentTarget || event.target;
    const originalBtnHtml = btn.innerHTML;
    btn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Analyse...';

    const labelsMap = {
        'Event': { main: '🎫 Billet' },
        'Shop': { main: '📦 Produit' },
        'Membership': { main: '🆔 Adhésion' },
        'Donation': { main: '❤️ Don' },
        'Crowdfunding': { main: '🚀 Contrib.' },
        'PaymentForm': { main: '💳 Article' },
        'Checkout': { main: '📦 Produit' },
        'product': { main: '📦 Produit' }
    };
    const labels = labelsMap[type] || labelsMap['Event'];
    
    try {
        const response = await fetch(`admin.php?action=analyze&org=${org}&form=${form}&type=${type}`);
        const data = await response.json();
        
        let html = `
            <div id="editor-container" class="mt-8 p-8 md:p-12 bg-white rounded-[2.5rem] border border-slate-100 animate-fade-in shadow-2xl">
                <div class="mb-10 flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <h3 class="text-2xl font-black text-slate-900 italic uppercase tracking-tight">Nouveau Board : ${name}</h3>
                        <p class="text-slate-400 text-xs font-black uppercase tracking-widest mt-1">Réglages par défaut du formulaire</p>
                    </div>
                    <div class="flex items-center gap-2 bg-slate-50 px-4 py-2 rounded-xl">
                        <span class="text-[10px] font-black text-slate-400 uppercase">Type :</span>
                        <span class="text-[10px] font-black text-blue-600 uppercase">${type}</span>
                    </div>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left border-separate border-spacing-y-3">
                        <thead>
                            <tr class="text-slate-400 uppercase tracking-widest text-[10px] font-black">
                                <th class="px-4 py-2">Visible</th>
                                <th class="px-4 py-2">Source HelloAsso</th>
                                <th class="px-4 py-2">Nom Affiché</th>
                                <th class="px-4 py-2">Catégorie</th>
                                <th class="px-4 py-2">Bloc</th>
                            </tr>
                        </thead>
                        <tbody id="rules-body">
            `;

        const ruleList = data.apiItems || [];

        ruleList.forEach((item, i) => {
            const pattern = item.pattern;
            const isMain = item.isMain;
            html += `
                <tr class="rule-row group" data-pattern="${pattern}">
                    <td class="py-3 px-4 bg-slate-50 first:rounded-l-2xl">
                        <input type="checkbox" class="rule-visible w-5 h-5 accent-blue-600 cursor-pointer" checked>
                    </td>
                    <td class="py-3 px-4 bg-slate-50 font-bold text-slate-400 text-[10px] uppercase truncate max-w-[200px]" title="${pattern}">
                        ${pattern}
                    </td>
                    <td class="py-3 px-4 bg-slate-50">
                        <input type="text" class="display-label input-soft !py-2 !text-sm" value="${pattern}">
                    </td>
                    <td class="py-3 px-4 bg-slate-50">
                        <select class="rule-type input-soft !py-2 !text-xs font-black uppercase">
                            <option value="Billet" ${isMain ? 'selected' : ''}>${labels.main}</option>
                            <option value="Option" ${!isMain ? 'selected' : ''}>📊 Option</option>
                            <option value="Ignorer">🚫 Cacher</option>
                        </select>
                    </td>
                    <td class="py-3 px-4 bg-slate-50 last:rounded-r-2xl">
                        <input type="text" class="rule-group input-soft !py-2 !text-xs uppercase" placeholder="DIVERS">
                    </td>
                </tr>
            `;
        });

        html += `</tbody></table></div>
                <div class="mt-12 flex flex-col md:flex-row justify-end items-center gap-6">
                    <button onclick="location.reload()" class="text-slate-400 hover:text-slate-600 font-black uppercase text-xs tracking-widest transition">Annuler</button>
                    <button onclick="saveFullCampaign('${org}','${form}','${type}','${name.replace(/'/g, "\\'")}')" class="w-full md:w-auto bg-blue-600 hover:bg-blue-700 px-12 py-5 rounded-[1.5rem] font-black text-white shadow-xl shadow-blue-100 transition transform active:scale-95 text-xs uppercase tracking-widest flex items-center justify-center gap-3">
                        <i class="fa-solid fa-rocket"></i> Lancer le Board
                    </button>
                </div>
            </div>`;
        
        const container = document.getElementById('config-zone');
        container.innerHTML = html;
        container.scrollIntoView({ behavior: 'smooth' });
        btn.innerHTML = originalBtnHtml;
    } catch (e) {
        console.error(e);
        alert("Erreur lors de l'analyse.");
        btn.innerHTML = originalBtnHtml;
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