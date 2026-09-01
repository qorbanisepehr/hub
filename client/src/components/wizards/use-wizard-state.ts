import { useCallback, useEffect, useMemo, useState } from "react";

export type WizardStep = {
    label: string;
    description?: string;
    /** Optional numeric id. Keyed tabs may omit it and use `key` instead. */
    id?: string | number;
    /** Optional semantic key. When present, the URL hash tracks the key and the
     * hook exposes `currentKey`/`goToKey`. Numeric steppers pass steps without
     * keys and keep the index-based `currentStep`/`goToStep(index)`. */
    key?: string;
};

function getStepKeyFromHash(keys: readonly string[]): string | undefined {
    const hash = window.location.hash.replace("#", "");
    return keys.find((k) => k === hash);
}

function getStepIndexFromHash(totalSteps: number): number {
    const hash = window.location.hash.replace("#", "");
    const step = parseInt(hash, 10);
    if (!isNaN(step) && step >= 0 && step < totalSteps) {
        return step;
    }
    return 0;
}

export function useWizardState(steps: readonly WizardStep[]) {
    const keys = useMemo(
        () => steps.map((s) => s.key).filter((k): k is string => !!k),
        [steps],
    );
    // Keyed mode only for steps that carry a semantic key AND no numeric id.
    // The cv/questionnaire steppers pass `{ id, key, ... }` and keep the
    // numeric hash/index behavior; the keyed tabs (employee profile) omit id.
    const isKeyed =
        keys.length === steps.length &&
        keys.length > 0 &&
        steps.every((s) => s.id === undefined);

    const [currentKey, setCurrentKey] = useState<string | undefined>(() =>
        isKeyed ? getStepKeyFromHash(keys) ?? keys[0] : undefined,
    );

    const [currentStep, setCurrentStep] = useState<number>(() =>
        isKeyed ? 0 : getStepIndexFromHash(steps.length),
    );

    useEffect(() => {
        const onHashChange = () => {
            if (isKeyed) {
                const key = getStepKeyFromHash(keys) ?? keys[0];
                if (key !== currentKey) setCurrentKey(key);
            } else {
                const step = getStepIndexFromHash(steps.length);
                if (step !== currentStep) setCurrentStep(step);
            }
        };
        window.addEventListener("hashchange", onHashChange);
        return () => window.removeEventListener("hashchange", onHashChange);
    }, [isKeyed, keys, currentKey, currentStep, steps.length]);

    const goToStep = useCallback(
        (step: number) => {
            if (isKeyed) {
                const key = steps[step]?.key;
                if (!key) return;
                setCurrentKey(key);
                window.location.hash = `#${key}`;
            } else {
                setCurrentStep(step);
                window.location.hash = `#${step}`;
            }
        },
        [isKeyed, steps],
    );

    const goToKey = useCallback(
        (key: string) => {
            if (!isKeyed) return;
            setCurrentKey(key);
            window.location.hash = `#${key}`;
        },
        [isKeyed],
    );

    const keyIndex = useMemo(() => {
        if (!isKeyed || !currentKey) return 0;
        return Math.max(
            0,
            steps.findIndex((s) => s.key === currentKey),
        );
    }, [isKeyed, currentKey, steps]);

    const activeKey = isKeyed ? currentKey : undefined;

    return {
        currentStep: isKeyed ? keyIndex : currentStep,
        goToStep,
        goToKey,
        currentKey: activeKey,
        isFirstStep: (isKeyed ? keyIndex : currentStep) === 0,
        isLastStep: (isKeyed ? keyIndex : currentStep) === steps.length - 1,
        totalSteps: steps.length,
    };
}
