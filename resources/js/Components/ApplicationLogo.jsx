/**
 * Logo texte Gogab : « Go » + « gab » en vert émeraude + point jaune soleil.
 * `light` : version pour fond bleu (texte blanc).
 */
export default function ApplicationLogo({ className = '', light = false }) {
    return (
        <span
            className={`inline-flex items-baseline font-extrabold tracking-tight ${
                light ? 'text-white' : 'text-secondary'
            } ${className}`}
        >
            Go
            <span className="text-primary">gab</span>
            <span
                className="ml-0.5 inline-block h-[0.3em] w-[0.3em] rounded-full bg-accent"
                aria-hidden="true"
            />
        </span>
    );
}
