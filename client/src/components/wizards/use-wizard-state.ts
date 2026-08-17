import { useCallback, useEffect, useState } from "react";

type WizardStep = {
    id: string | number;
    label: string;
    description?: string;
};

function getStepFromHash(totalSteps: number): number {
    const hash = window.location.hash.replace("#", "");
    const step = parseInt(hash, 10);
    if (!isNaN(step) && step >= 0 && step < totalSteps) {
        return step;
    }
    return 0;
}

function setStepHash(step: number) {
    window.location.hash = `#${step}`;
}

export function useWizardState(steps: readonly WizardStep[]) {
    const [currentStep, setCurrentStep] = useState(() =>
        getStepFromHash(steps.length),
    );

    useEffect(() => {
        const onHashChange = () => {
            const step = getStepFromHash(steps.length);
            if (step !== currentStep) {
                setCurrentStep(step);
            }
        };
        window.addEventListener("hashchange", onHashChange);
        return () => window.removeEventListener("hashchange", onHashChange);
    }, [currentStep, steps.length]);

    const goToStep = useCallback(
        (step: number) => {
            setCurrentStep(step);
            setStepHash(step);
        },
        [],
    );

    return {
        currentStep,
        goToStep,
        isFirstStep: currentStep === 0,
        isLastStep: currentStep === steps.length - 1,
        totalSteps: steps.length,
    };
}
