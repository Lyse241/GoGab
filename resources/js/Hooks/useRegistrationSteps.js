import axios from 'axios';
import { useRef, useState } from 'react';

/**
 * Navigation d'une inscription en plusieurs étapes, sur un seul useForm Inertia
 * (revenir en arrière ne perd aucune donnée) :
 *
 * - next() : contrôles immédiats (`validate(step, data)`), puis vérification serveur de l'étape
 *   (POST `checkUrl` avec `step` = numéro d'étape à partir de 1 et les champs de l'étape) ;
 * - goTo(step) : change d'étape et remonte en haut du formulaire ;
 * - goToErrors(errors) : après un refus du serveur à l'envoi final, revient à la première étape en erreur.
 *
 * `stepFields` : noms des champs de chaque étape sauf la dernière (les autres sont dans la dernière).
 */
export default function useRegistrationSteps({ form, stepFields, checkUrl, validate }) {
    const [step, setStep] = useState(0);
    const [checking, setChecking] = useState(false);
    const topRef = useRef(null);

    const stepOf = (errorKey) => {
        const field = errorKey.split('.')[0];
        const index = stepFields.findIndex((fields) => fields.includes(field));

        return index === -1 ? stepFields.length : index;
    };

    const focusField = (key) => {
        const element = document.getElementById(key) ?? document.getElementById(key.replaceAll('.', '-'));
        element?.focus();
    };

    const goTo = (next) => {
        setStep(next);
        topRef.current?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };

    const showErrors = (errors) => {
        form.setError(errors);
        focusField(Object.keys(errors)[0]);
    };

    const next = async () => {
        const clientErrors = validate(step, form.data);
        if (Object.keys(clientErrors).length > 0) {
            showErrors(clientErrors);
            return;
        }

        setChecking(true);
        try {
            const fields = Object.fromEntries(stepFields[step].map((field) => [field, form.data[field]]));
            await axios.post(checkUrl, { step: step + 1, ...fields });
            form.clearErrors();
            goTo(step + 1);
        } catch (error) {
            if (error.response?.status === 422) {
                showErrors(
                    Object.fromEntries(Object.entries(error.response.data.errors ?? {}).map(([key, messages]) => [key, messages[0]])),
                );
            } else {
                // Réseau indisponible : on laisse avancer, l'envoi final revalidera tout.
                goTo(step + 1);
            }
        } finally {
            setChecking(false);
        }
    };

    const goToErrors = (errors) => goTo(Math.min(...Object.keys(errors).map(stepOf)));

    return { step, goTo, next, checking, topRef, goToErrors };
}
