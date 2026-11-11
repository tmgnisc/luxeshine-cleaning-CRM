"use client";

import { Button } from "@/components/ui/button";
import { Card, CardContent } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { Textarea } from "@/components/ui/textarea";
import { usePublicSettings } from "@/hooks/use-settings";
import { apiSend } from "@/lib/api";
import { applyApiErrors } from "@/lib/form";
import { zodResolver } from "@hookform/resolvers/zod";
import { Mail, MapPin, Phone } from "lucide-react";
import { useState } from "react";
import { useForm } from "react-hook-form";
import { toast } from "sonner";
import { z } from "zod";

const schema = z.object({
  name: z.string().min(2, "Please enter your name."),
  email: z.string().email("Enter a valid email."),
  phone: z.string().optional(),
  message: z.string().min(5, "Please enter a message."),
});
type FormValues = z.infer<typeof schema>;

export default function ContactPage() {
  const { data: settings } = usePublicSettings();
  const [sent, setSent] = useState(false);
  const form = useForm<FormValues>({ resolver: zodResolver(schema), defaultValues: { name: "", email: "", phone: "", message: "" } });

  const onSubmit = async (values: FormValues) => {
    try {
      await apiSend("post", "public/contact", values);
      setSent(true);
      toast.success("Message sent — we'll be in touch soon!");
      form.reset();
    } catch (e) {
      applyApiErrors(e, form.setError);
    }
  };

  return (
    <div className="mx-auto max-w-5xl px-4 py-14 sm:px-6">
      <h1 className="text-3xl font-semibold tracking-tight">Get in touch</h1>
      <p className="mt-2 text-muted-foreground">Questions about a booking or our services? We&apos;re here to help.</p>

      <div className="mt-10 grid gap-8 lg:grid-cols-5">
        <div className="space-y-4 lg:col-span-2">
          {settings?.support_phone ? <ContactRow icon={Phone} label="Phone" value={settings.support_phone} /> : null}
          {settings?.support_email ? <ContactRow icon={Mail} label="Email" value={settings.support_email} /> : null}
          {settings?.office_address ? <ContactRow icon={MapPin} label="Office" value={settings.office_address} /> : null}
        </div>

        <Card className="rounded-2xl lg:col-span-3">
          <CardContent className="p-6">
            {sent ? (
              <div className="rounded-xl bg-primary/5 p-6 text-center text-sm">Thanks for reaching out — we&apos;ll reply shortly.</div>
            ) : (
              <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4">
                <div className="grid gap-4 sm:grid-cols-2">
                  <div className="space-y-1.5"><Label>Name</Label><Input {...form.register("name")} />{form.formState.errors.name && <p className="text-xs text-destructive">{form.formState.errors.name.message}</p>}</div>
                  <div className="space-y-1.5"><Label>Phone</Label><Input {...form.register("phone")} /></div>
                </div>
                <div className="space-y-1.5"><Label>Email</Label><Input type="email" {...form.register("email")} />{form.formState.errors.email && <p className="text-xs text-destructive">{form.formState.errors.email.message}</p>}</div>
                <div className="space-y-1.5"><Label>Message</Label><Textarea rows={5} {...form.register("message")} />{form.formState.errors.message && <p className="text-xs text-destructive">{form.formState.errors.message.message}</p>}</div>
                <Button type="submit" disabled={form.formState.isSubmitting}>{form.formState.isSubmitting ? "Sending…" : "Send message"}</Button>
              </form>
            )}
          </CardContent>
        </Card>
      </div>
    </div>
  );
}

function ContactRow({ icon: Icon, label, value }: { icon: typeof Phone; label: string; value: string }) {
  return (
    <div className="flex items-start gap-3 rounded-2xl border bg-card p-4">
      <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary"><Icon className="h-5 w-5" /></div>
      <div><p className="text-xs text-muted-foreground">{label}</p><p className="font-medium">{value}</p></div>
    </div>
  );
}
