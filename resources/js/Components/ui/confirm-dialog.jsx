import { AlertTriangle, Trash2 } from 'lucide-react';
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from '@/components/ui/dialog';
import { Button } from '@/components/ui/button';

export function ConfirmDialog({
    open,
    onClose,
    title,
    description,
    confirmLabel = 'Confirmer',
    variant = 'destructive',
    onConfirm,
}) {
    const handleConfirm = () => {
        onConfirm?.();
        onClose();
    };

    const isDestructive = variant === 'destructive';

    return (
        <Dialog open={open} onOpenChange={onClose}>
            <DialogContent className="max-w-sm">
                <DialogHeader>
                    <div className="flex items-start gap-3">
                        {isDestructive && (
                            <div className="shrink-0 h-9 w-9 rounded-full bg-red-100 flex items-center justify-center mt-0.5">
                                <Trash2 className="h-4 w-4 text-danger" />
                            </div>
                        )}
                        {!isDestructive && (
                            <div className="shrink-0 h-9 w-9 rounded-full bg-amber-100 flex items-center justify-center mt-0.5">
                                <AlertTriangle className="h-4 w-4 text-amber-600" />
                            </div>
                        )}
                        <div>
                            <DialogTitle className="text-base">{title}</DialogTitle>
                            {description && (
                                <DialogDescription className="mt-1 text-sm">
                                    {description}
                                </DialogDescription>
                            )}
                        </div>
                    </div>
                </DialogHeader>
                <DialogFooter className="mt-2">
                    <Button variant="outline" onClick={onClose}>
                        Annuler
                    </Button>
                    <Button variant={variant} onClick={handleConfirm}>
                        {confirmLabel}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
