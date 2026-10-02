import { Bar, BarChart, CartesianGrid, LabelList, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

// Charte : vert Gogab (primary-600, contraste ≥ 3:1 sur blanc, vérifié), grille et axes discrets.
const BAR = '#00A86B';
const GRID = '#e5e5e5';
const TICK = '#737373';

function ChartTooltip({ active, payload }) {
    if (!active || !payload?.length) {
        return null;
    }
    const day = payload[0].payload;

    return (
        <div className="rounded-xl bg-white px-3 py-2 text-sm shadow-lg ring-1 ring-gray-200">
            <p className="font-semibold text-secondary-900">{day.label}</p>
            <p className="text-gray-700">
                {day.orders} commande{day.orders > 1 ? 's' : ''}
            </p>
            <p className="text-gray-500">dont {day.delivered} livrée{day.delivered > 1 ? 's' : ''}</p>
        </div>
    );
}

/**
 * Commandes créées par jour sur les 7 derniers jours (une seule série : pas de légende, le
 * titre la nomme). Valeur affichée sur le jour le plus chargé ; les autres au survol et dans
 * la vue tableau.
 */
export default function OrdersWeekChart({ data }) {
    const max = Math.max(0, ...data.map((day) => day.orders));
    const peak = data.findIndex((day) => day.orders === max && max > 0);

    return (
        <div>
            <div className="h-60" role="img" aria-label={`Commandes des 7 derniers jours : ${data.map((day) => `${day.label} ${day.orders}`).join(', ')}`}>
                <ResponsiveContainer width="100%" height="100%">
                    <BarChart data={data} margin={{ top: 20, right: 4, bottom: 0, left: -20 }} barCategoryGap="30%">
                        <CartesianGrid vertical={false} stroke={GRID} />
                        <XAxis dataKey="label" tickLine={false} axisLine={{ stroke: GRID }} tick={{ fill: TICK, fontSize: 12 }} interval={0} />
                        <YAxis allowDecimals={false} tickLine={false} axisLine={false} tick={{ fill: TICK, fontSize: 12 }} width={44} />
                        <Tooltip content={<ChartTooltip />} cursor={{ fill: 'rgba(0, 168, 107, 0.08)' }} />
                        <Bar dataKey="orders" fill={BAR} radius={[4, 4, 0, 0]} maxBarSize={36} isAnimationActive={false}>
                            <LabelList
                                dataKey="orders"
                                position="top"
                                content={({ x, y, width, value, index }) =>
                                    index === peak ? (
                                        <text x={x + width / 2} y={y - 6} textAnchor="middle" fontSize={12} fontWeight={600} fill="#262626">
                                            {value}
                                        </text>
                                    ) : null
                                }
                            />
                        </Bar>
                    </BarChart>
                </ResponsiveContainer>
            </div>

            <details className="mt-2 text-sm">
                <summary className="min-h-tap cursor-pointer py-2 text-gray-600 hover:text-gray-900">Voir en tableau</summary>
                <table className="w-full text-left">
                    <thead>
                        <tr className="border-b border-gray-200 text-xs text-gray-500">
                            <th className="py-1.5 font-medium">Jour</th>
                            <th className="py-1.5 text-right font-medium">Commandes</th>
                            <th className="py-1.5 text-right font-medium">Livrées</th>
                        </tr>
                    </thead>
                    <tbody className="tabular-nums">
                        {data.map((day) => (
                            <tr key={day.date} className="border-b border-gray-100">
                                <td className="py-1.5">{day.label}</td>
                                <td className="py-1.5 text-right">{day.orders}</td>
                                <td className="py-1.5 text-right">{day.delivered}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </details>
        </div>
    );
}
