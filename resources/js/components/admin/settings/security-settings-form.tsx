import { useForm } from '@inertiajs/react';
import SettingsController from '@/actions/App/Http/Controllers/Admin/SettingsController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardContent,
    CardDescription,
    CardFooter,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Switch } from '@/components/ui/switch';

export type SecuritySettings = {
    require_two_factor: boolean;
};

export function SecuritySettingsForm({ values }: { values: SecuritySettings }) {
    const form = useForm({ require_two_factor: values.require_two_factor });

    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                form.put(SettingsController.update.url('security'), {
                    preserveScroll: true,
                });
            }}
        >
            <Card>
                <CardHeader>
                    <CardTitle>Staff sign-in</CardTitle>
                    <CardDescription>
                        Applies to every staff account.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <div className="flex items-start justify-between gap-4">
                        <div className="grid gap-1">
                            <Label htmlFor="require_two_factor">
                                Require two-factor authentication
                            </Label>
                            <p className="text-xs text-muted-foreground">
                                Staff without an authenticator app set up are
                                sent to their security page before they can use
                                the admin. When off, two-factor is optional.
                            </p>
                            <InputError
                                message={form.errors.require_two_factor}
                            />
                        </div>
                        <Switch
                            id="require_two_factor"
                            checked={form.data.require_two_factor}
                            onCheckedChange={(checked) =>
                                form.setData('require_two_factor', checked)
                            }
                        />
                    </div>
                </CardContent>
                <CardFooter>
                    <Button
                        type="submit"
                        disabled={form.processing || !form.isDirty}
                    >
                        Save security settings
                    </Button>
                </CardFooter>
            </Card>
        </form>
    );
}
