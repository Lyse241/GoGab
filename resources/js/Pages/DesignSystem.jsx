import Badge from '@/Components/UI/Badge';
import Button from '@/Components/UI/Button';
import Card, { CardFooter, CardHeader } from '@/Components/UI/Card';
import Checkbox from '@/Components/UI/Checkbox';
import ConfirmDialog from '@/Components/UI/ConfirmDialog';
import EmptyState from '@/Components/UI/EmptyState';
import FileUpload from '@/Components/UI/FileUpload';
import Input from '@/Components/UI/Input';
import Modal from '@/Components/UI/Modal';
import Pagination from '@/Components/UI/Pagination';
import Select from '@/Components/UI/Select';
import Skeleton, { SkeletonCard, SkeletonText } from '@/Components/UI/Skeleton';
import StatusBadge from '@/Components/UI/StatusBadge';
import Stepper from '@/Components/UI/Stepper';
import Tabs from '@/Components/UI/Tabs';
import Textarea from '@/Components/UI/Textarea';
import { useToast } from '@/Components/UI/Toast';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatFCFA } from '@/utils/format';
import { Head, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    Bike,
    ClipboardList,
    Phone,
    Plus,
    Search,
    ShoppingBag,
    Star,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';

function Section({ id, title, description, children }) {
    return (
        <section id={id} className="scroll-mt-40 border-t border-gray-200 py-10 first:border-t-0">
            <h2 className="text-xl font-bold text-secondary-900">{title}</h2>
            {description && <p className="mt-1 max-w-2xl text-sm text-gray-600">{description}</p>}
            <div className="mt-6">{children}</div>
        </section>
    );
}

function Swatch({ name, className, hex, text = 'text-white' }) {
    return (
        <div className="overflow-hidden rounded-2xl ring-1 ring-gray-200">
            <div className={`flex h-20 items-end p-3 text-sm font-semibold ${className} ${text}`}>{name}</div>
            <p className="bg-white px-3 py-2 font-mono text-xs text-gray-600">{hex}</p>
        </div>
    );
}

function Scale({ name, prefix }) {
    const steps = [50, 100, 200, 300, 400, 500, 600, 700, 800, 900];

    return (
        <div>
            <p className="mb-2 text-sm font-semibold text-gray-800">{name}</p>
            <div className="grid grid-cols-10 overflow-hidden rounded-xl ring-1 ring-gray-200">
                {steps.map((step) => (
                    <div key={step} className={`h-10 ${prefix}-${step}`} title={`${prefix}-${step}`} />
                ))}
            </div>
        </div>
    );
}

// Classes écrites en entier pour que Tailwind les génère.
const scales = [
    ['Vert émeraude (primary)', 'bg-primary'],
    ['Bleu profond (secondary)', 'bg-secondary'],
    ['Jaune soleil (accent)', 'bg-accent'],
    ['Gris neutres', 'bg-gray'],
    ['Succès', 'bg-success'],
    ['Alerte', 'bg-warning'],
    ['Erreur', 'bg-danger'],
    ['Information', 'bg-info'],
];
// bg-primary-50 bg-primary-100 bg-primary-200 bg-primary-300 bg-primary-400 bg-primary-500 bg-primary-600 bg-primary-700 bg-primary-800 bg-primary-900
// bg-secondary-50 bg-secondary-100 bg-secondary-200 bg-secondary-300 bg-secondary-400 bg-secondary-500 bg-secondary-600 bg-secondary-700 bg-secondary-800 bg-secondary-900
// bg-accent-50 bg-accent-100 bg-accent-200 bg-accent-300 bg-accent-400 bg-accent-500 bg-accent-600 bg-accent-700 bg-accent-800 bg-accent-900
// bg-gray-50 bg-gray-100 bg-gray-200 bg-gray-300 bg-gray-400 bg-gray-500 bg-gray-600 bg-gray-700 bg-gray-800 bg-gray-900
// bg-success-50 bg-success-100 bg-success-200 bg-success-300 bg-success-400 bg-success-500 bg-success-600 bg-success-700 bg-success-800 bg-success-900
// bg-warning-50 bg-warning-100 bg-warning-200 bg-warning-300 bg-warning-400 bg-warning-500 bg-warning-600 bg-warning-700 bg-warning-800 bg-warning-900
// bg-danger-50 bg-danger-100 bg-danger-200 bg-danger-300 bg-danger-400 bg-danger-500 bg-danger-600 bg-danger-700 bg-danger-800 bg-danger-900
// bg-info-50 bg-info-100 bg-info-200 bg-info-300 bg-info-400 bg-info-500 bg-info-600 bg-info-700 bg-info-800 bg-info-900

const fakePaginator = {
    current_page: 3,
    last_page: 8,
    prev_page_url: '#page-2',
    next_page_url: '#page-4',
    links: [
        { url: '#page-2', label: 'Précédent', active: false },
        { url: '#page-1', label: '1', active: false },
        { url: '#page-2', label: '2', active: false },
        { url: '#page-3', label: '3', active: true },
        { url: '#page-4', label: '4', active: false },
        { url: null, label: '...', active: false },
        { url: '#page-8', label: '8', active: false },
        { url: '#page-4', label: 'Suivant', active: false },
    ],
};

const nav = [
    ['couleurs', 'Couleurs'],
    ['typographie', 'Typographie'],
    ['boutons', 'Boutons'],
    ['formulaires', 'Formulaires'],
    ['cartes', 'Cartes'],
    ['badges', 'Badges'],
    ['onglets', 'Onglets'],
    ['stepper', 'Stepper'],
    ['modales', 'Modales'],
    ['toasts', 'Toasts'],
    ['chargement', 'Chargement'],
    ['vide', 'État vide'],
    ['pagination', 'Pagination'],
    ['fichiers', 'Fichiers'],
    ['montants', 'Montants'],
];

export default function DesignSystem() {
    const { statuses } = usePage().props;
    const toast = useToast();
    const [modalOpen, setModalOpen] = useState(false);
    const [confirmOpen, setConfirmOpen] = useState(false);
    const [confirmLoading, setConfirmLoading] = useState(false);
    const [step, setStep] = useState(1);
    const [file, setFile] = useState(null);
    const [idCard, setIdCard] = useState(null);
    const [note, setNote] = useState('');
    const [loadingDemo, setLoadingDemo] = useState(false);

    const steps = [
        { label: 'Identité', description: 'Nom, téléphone' },
        { label: 'Véhicule', description: 'Type, plaque' },
        { label: 'Documents', description: 'CIN, permis' },
        { label: 'Validation', description: 'Vérification admin' },
    ];

    const confirmDelete = () => {
        setConfirmLoading(true);
        setTimeout(() => {
            setConfirmLoading(false);
            setConfirmOpen(false);
            toast.success('Produit supprimé.');
        }, 1200);
    };

    return (
        <PublicLayout>
            <Head title="Design system" />

            <div className="pt-6">
                <Badge color="accent">Environnement local</Badge>
                <h1 className="mt-3 text-3xl font-bold text-secondary-900">Design system Gogab</h1>
                <p className="mt-2 max-w-2xl text-gray-600">
                    Charte et composants réutilisables (<code className="text-sm">resources/js/Components/UI</code>).
                    Vérifiez l’affichage sur téléphone : la plupart des utilisateurs commandent depuis leur mobile.
                </p>

                <nav
                    aria-label="Sections"
                    className="no-scrollbar sticky top-[8.5rem] z-10 -mx-4 mt-6 flex gap-2 overflow-x-auto bg-gray-50/95 px-4 py-2 backdrop-blur sm:top-16 sm:mx-0 sm:flex-wrap sm:px-0"
                >
                    {nav.map(([id, label]) => (
                        <a
                            key={id}
                            href={`#${id}`}
                            className="shrink-0 rounded-full bg-white px-3 py-1.5 text-sm font-medium text-gray-700 ring-1 ring-gray-200 hover:bg-gray-100"
                        >
                            {label}
                        </a>
                    ))}
                </nav>
            </div>

            <Section
                id="couleurs"
                title="Couleurs"
                description="60 % vert émeraude, 30 % bleu profond, 10 % jaune soleil. Texte blanc sur primary-600 (contraste AA), jamais sur le jaune."
            >
                <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                    <Swatch name="Vert émeraude" className="bg-primary" hex="#00A86B" />
                    <Swatch name="Bleu profond" className="bg-secondary" hex="#1E3A8A" />
                    <Swatch name="Jaune soleil" className="bg-accent" hex="#FBBF24" text="text-secondary-900" />
                    <Swatch name="Bouton (primary-600)" className="bg-primary-600" hex="#008660" />
                </div>
                <div className="mt-6 grid gap-5 lg:grid-cols-2">
                    {scales.map(([name, prefix]) => (
                        <Scale key={prefix} name={name} prefix={prefix} />
                    ))}
                </div>
            </Section>

            <Section id="typographie" title="Typographie" description="Poppins (Google Fonts), graisses 400 à 800.">
                <div className="space-y-3">
                    <p className="text-3xl font-bold text-secondary-900">Titre de page — text-3xl bold</p>
                    <p className="text-xl font-bold text-secondary-900">Titre de section — text-xl bold</p>
                    <p className="text-base font-semibold text-gray-900">Titre de carte — text-base semibold</p>
                    <p className="text-base text-gray-700">
                        Texte courant — text-base. Poulet nyembwe, feuilles de manioc, capitaine braisé.
                    </p>
                    <p className="text-sm text-gray-500">Texte secondaire — text-sm gray-500</p>
                </div>
            </Section>

            <Section
                id="boutons"
                title="Boutons"
                description="Taille md = 44 px de haut (cible tactile). lg pour les actions principales en bas d’écran."
            >
                <div className="space-y-6">
                    <div className="flex flex-wrap items-center gap-3">
                        <Button>Primaire</Button>
                        <Button variant="secondary">Secondaire</Button>
                        <Button variant="accent">Accent</Button>
                        <Button variant="outline">Contour</Button>
                        <Button variant="ghost">Discret</Button>
                        <Button variant="danger">Danger</Button>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Button size="sm">Petit</Button>
                        <Button size="md">Moyen</Button>
                        <Button size="lg">Grand</Button>
                        <Button size="icon" variant="outline" aria-label="Rechercher">
                            <Search className="h-5 w-5" aria-hidden="true" />
                        </Button>
                        <Button size="icon-sm" variant="ghost" aria-label="Supprimer">
                            <Trash2 className="h-4 w-4" aria-hidden="true" />
                        </Button>
                    </div>
                    <div className="flex flex-wrap items-center gap-3">
                        <Button icon={Plus}>Ajouter un produit</Button>
                        <Button variant="secondary" iconRight={ArrowRight}>
                            Continuer
                        </Button>
                        <Button
                            loading={loadingDemo}
                            onClick={() => {
                                setLoadingDemo(true);
                                setTimeout(() => setLoadingDemo(false), 1500);
                            }}
                        >
                            {loadingDemo ? 'Envoi…' : 'Tester le chargement'}
                        </Button>
                        <Button disabled>Désactivé</Button>
                    </div>
                    <div className="max-w-sm">
                        <Button size="lg" fullWidth icon={ShoppingBag}>
                            Commander · {formatFCFA(12500)}
                        </Button>
                    </div>
                </div>
            </Section>

            <Section id="formulaires" title="Formulaires" description="Libellé, aide et erreur reliés au champ (accessibilité).">
                <div className="grid gap-5 sm:grid-cols-2">
                    <Input id="ds-name" label="Nom complet" placeholder="Ex. Marie Ndong" required />
                    <Input
                        id="ds-phone"
                        label="Téléphone"
                        icon={Phone}
                        type="tel"
                        placeholder="077 12 34 56"
                        hint="Le livreur vous appellera sur ce numéro."
                    />
                    <Input id="ds-email" label="E-mail" defaultValue="adresse@invalide" error="Saisissez une adresse e-mail valide." />
                    <Input id="ds-cash" label="Montant remis en espèces" type="number" inputMode="numeric" suffix="FCFA" placeholder="10000" />
                    <Select
                        id="ds-neighborhood"
                        label="Quartier"
                        placeholder="Choisissez votre quartier"
                        options={[
                            { value: 1, label: 'Louis' },
                            { value: 2, label: 'Glass' },
                            { value: 3, label: 'Nzeng-Ayong' },
                        ]}
                        required
                    />
                    <Select id="ds-vehicle" label="Véhicule" error="Choisissez un type de véhicule." defaultValue="">
                        <option value="">—</option>
                        <option value="moto">Moto</option>
                        <option value="bicycle">Vélo</option>
                        <option value="car">Voiture</option>
                    </Select>
                    <Textarea
                        id="ds-landmarks"
                        label="Repères pour le livreur"
                        placeholder="Portail bleu après la pharmacie…"
                        maxLength={500}
                        value={note}
                        onChange={(event) => setNote(event.target.value)}
                        wrapperClassName="sm:col-span-2"
                    />
                    <div className="sm:col-span-2">
                        <Checkbox id="ds-terms" label="J’accepte les conditions d’utilisation" description="Obligatoire pour créer un compte." />
                        <Checkbox id="ds-available" label="Disponible pour des courses" defaultChecked />
                        <Checkbox id="ds-error" label="Case en erreur" error="Cette case doit être cochée." />
                    </div>
                    <Input id="ds-disabled" label="Champ désactivé" defaultValue="Non modifiable" disabled />
                </div>
            </Section>

            <Section id="cartes" title="Cartes">
                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    <Card>
                        <CardHeader
                            title="Commande GG-000123"
                            description="Chez Maman Ngoye · Glass"
                            action={<StatusBadge status="en_preparation" />}
                        />
                        <p className="text-sm text-gray-600">2 × Poulet nyembwe, 1 × Jus de bissap</p>
                        <p className="mt-3 text-lg font-bold text-secondary-900">{formatFCFA(10000)}</p>
                        <CardFooter>
                            <Button variant="outline" size="sm">
                                Détails
                            </Button>
                            <Button size="sm">Suivre</Button>
                        </CardFooter>
                    </Card>
                    <Card href="#cartes" padding="none" className="overflow-hidden">
                        <div className="aspect-[16/9] bg-gradient-to-br from-primary-400 to-secondary" />
                        <div className="p-4">
                            <div className="flex items-center justify-between gap-2">
                                <h3 className="font-semibold text-gray-900">Le Braisé du Bord de Mer</h3>
                                <Badge color="accent" icon={Star}>
                                    4,8
                                </Badge>
                            </div>
                            <p className="mt-1 text-sm text-gray-500">Restaurant · Louis · 25–35 min</p>
                        </div>
                    </Card>
                    <Card padding="lg" className="bg-secondary text-white ring-0">
                        <Bike className="h-8 w-8 text-accent" aria-hidden="true" />
                        <p className="mt-3 text-lg font-semibold">Devenez livreur Gogab</p>
                        <p className="mt-1 text-sm text-secondary-100">Gagnez de l’argent dans votre quartier.</p>
                        <Button variant="accent" className="mt-5" iconRight={ArrowRight}>
                            S’inscrire
                        </Button>
                    </Card>
                </div>
            </Section>

            <Section id="badges" title="Badges" description="StatusBadge lit libellés et couleurs depuis les enums PHP (prop partagée « statuses »).">
                <div className="space-y-5">
                    <div className="flex flex-wrap gap-2">
                        {['primary', 'secondary', 'accent', 'neutral', 'success', 'warning', 'danger', 'info'].map((color) => (
                            <Badge key={color} color={color}>
                                {color}
                            </Badge>
                        ))}
                    </div>
                    <div>
                        <p className="mb-2 text-sm font-semibold text-gray-800">Statuts de commande</p>
                        <div className="flex flex-wrap gap-2">
                            {Object.keys(statuses.order).map((status) => (
                                <StatusBadge key={status} status={status} />
                            ))}
                        </div>
                    </div>
                    <div>
                        <p className="mb-2 text-sm font-semibold text-gray-800">Statuts de compte</p>
                        <div className="flex flex-wrap gap-2">
                            {Object.keys(statuses.account).map((status) => (
                                <StatusBadge key={status} type="account" status={status} />
                            ))}
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-2">
                        <StatusBadge status="livree" size="sm" />
                        <StatusBadge status="livree" />
                        <StatusBadge status="livree" size="lg" />
                    </div>
                </div>
            </Section>

            <Section id="onglets" title="Onglets" description="Flèches gauche/droite pour naviguer au clavier ; défilement horizontal sur mobile.">
                <div className="space-y-8">
                    <Tabs
                        label="Commandes"
                        items={[
                            { value: 'new', label: 'Nouvelles', count: 3, content: <p className="text-sm text-gray-600">3 commandes à accepter.</p> },
                            { value: 'preparing', label: 'En préparation', count: 1, content: <p className="text-sm text-gray-600">1 commande en cuisine.</p> },
                            { value: 'done', label: 'Terminées', content: <p className="text-sm text-gray-600">Historique du jour.</p> },
                        ]}
                    />
                    <Tabs
                        variant="underline"
                        label="Compte"
                        items={[
                            { value: 'orders', label: 'Commandes', icon: ClipboardList, content: <p className="text-sm text-gray-600">Vos commandes.</p> },
                            { value: 'profile', label: 'Profil', content: <p className="text-sm text-gray-600">Vos informations.</p> },
                        ]}
                    />
                </div>
            </Section>

            <Section id="stepper" title="Stepper" description="Version compacte sur mobile, complète à partir de 640 px.">
                <Card>
                    <Stepper steps={steps} current={step} />
                    <CardFooter>
                        <Button variant="outline" onClick={() => setStep((s) => Math.max(0, s - 1))} disabled={step === 0}>
                            Précédent
                        </Button>
                        <Button onClick={() => setStep((s) => Math.min(steps.length - 1, s + 1))} disabled={step === steps.length - 1}>
                            Suivant
                        </Button>
                    </CardFooter>
                </Card>
            </Section>

            <Section id="modales" title="Modales" description="Bottom sheet sur mobile, centrée sur desktop. Échap et clic extérieur ferment.">
                <div className="flex flex-wrap gap-3">
                    <Button variant="secondary" onClick={() => setModalOpen(true)}>
                        Ouvrir une modale
                    </Button>
                    <Button variant="danger" icon={Trash2} onClick={() => setConfirmOpen(true)}>
                        Supprimer un produit
                    </Button>
                </div>

                <Modal
                    open={modalOpen}
                    onClose={() => setModalOpen(false)}
                    title="Refuser la commande"
                    description="Le client sera prévenu immédiatement."
                    footer={
                        <>
                            <Button variant="outline" onClick={() => setModalOpen(false)}>
                                Annuler
                            </Button>
                            <Button variant="danger" onClick={() => setModalOpen(false)}>
                                Refuser
                            </Button>
                        </>
                    }
                >
                    <Textarea id="ds-refusal" label="Motif du refus" placeholder="Rupture de stock…" rows={3} />
                </Modal>

                <ConfirmDialog
                    open={confirmOpen}
                    onClose={() => setConfirmOpen(false)}
                    onConfirm={confirmDelete}
                    loading={confirmLoading}
                    title="Supprimer « Poulet DG » ?"
                    message="Le produit disparaîtra du menu. Cette action est définitive."
                    confirmLabel="Supprimer"
                />
            </Section>

            <Section id="toasts" title="Toasts" description="Les messages flash Laravel (success, error, warning, info) s’affichent automatiquement.">
                <div className="flex flex-wrap gap-3">
                    <Button variant="outline" onClick={() => toast.success('Commande GG-000123 acceptée.')}>
                        Succès
                    </Button>
                    <Button variant="outline" onClick={() => toast.error('Cette commande a déjà été prise par un autre livreur.')}>
                        Erreur
                    </Button>
                    <Button variant="outline" onClick={() => toast.warning('Votre boutique est fermée : aucune commande ne sera reçue.')}>
                        Alerte
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => toast.info('Nouveau : suivez votre livreur étape par étape.', { title: 'Nouveauté' })}
                    >
                        Information
                    </Button>
                </div>
            </Section>

            <Section id="chargement" title="Chargement (Skeleton)">
                <div className="grid gap-4 sm:grid-cols-3">
                    <SkeletonCard />
                    <SkeletonCard />
                    <Card>
                        <div className="flex items-center gap-3">
                            <Skeleton className="h-12 w-12 rounded-full" />
                            <div className="flex-1 space-y-2">
                                <Skeleton className="h-4 w-1/2" />
                                <Skeleton className="h-3 w-1/3" />
                            </div>
                        </div>
                        <SkeletonText className="mt-4" lines={4} />
                    </Card>
                </div>
            </Section>

            <Section id="vide" title="État vide">
                <EmptyState
                    icon={ShoppingBag}
                    title="Votre panier est vide"
                    description="Parcourez les commerces de votre quartier et ajoutez vos produits préférés."
                    action={<Button iconRight={ArrowRight}>Voir les commerces</Button>}
                />
            </Section>

            <Section id="pagination" title="Pagination" description="Numéros de page sur desktop, « Page 3 sur 8 » sur mobile.">
                <Card>
                    <Pagination paginator={fakePaginator} />
                </Card>
            </Section>

            <Section id="fichiers" title="Envoi de fichiers" description="Glisser-déposer ou clic ; aperçu ; type et taille vérifiés avant l’envoi.">
                <div className="grid gap-5 sm:grid-cols-2">
                    <FileUpload
                        id="ds-photo"
                        label="Photo du véhicule (avant)"
                        hint="JPG, PNG ou WebP."
                        value={file}
                        onChange={setFile}
                        required
                    />
                    <FileUpload
                        id="ds-cin"
                        label="Carte d’identité (CIN)"
                        accept="image/jpeg,image/png,application/pdf"
                        maxSize={5 * 1024 * 1024}
                        hint="Photo ou PDF, 5 Mo maximum."
                        value={idCard}
                        onChange={setIdCard}
                    />
                    <FileUpload
                        id="ds-current"
                        label="Logo actuel"
                        current="https://picsum.photos/seed/gogab-logo/200/200"
                        onChange={() => {}}
                    />
                    <FileUpload id="ds-upload-error" label="Permis de conduire" error="Le permis de conduire est obligatoire." onChange={() => {}} />
                </div>
            </Section>

            <Section id="montants" title="Montants" description="formatFCFA() : séparateur de milliers, espaces insécables.">
                <ul className="space-y-1 font-mono text-sm text-gray-800">
                    {[0, 500, 4500, 12500, 150000, '8000.00'].map((value) => (
                        <li key={value}>
                            formatFCFA({JSON.stringify(value)}) → <strong>{formatFCFA(value)}</strong>
                        </li>
                    ))}
                </ul>
            </Section>
        </PublicLayout>
    );
}
