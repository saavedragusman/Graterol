/** BR-11: una desviación por encima del umbral configurado requiere atención. */
export function isAboveThreshold(deviation: string | number | null, threshold: string | number): boolean {
    if (deviation === null) {
        return false;
    }

    return Math.abs(Number(deviation)) > Number(threshold);
}
