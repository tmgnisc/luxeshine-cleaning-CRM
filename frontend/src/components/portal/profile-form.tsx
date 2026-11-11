"use client";

import { PersonAvatar } from "@/components/shared/person-avatar";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { http, ApiError } from "@/lib/api";
import { useAuth } from "@/providers/auth-provider";
import { useState } from "react";
import { toast } from "sonner";

export function ProfileForm() {
  const { user, refresh } = useAuth();
  const [name, setName] = useState(user?.name ?? "");
  const [phone, setPhone] = useState(user?.phone ?? "");
  const [avatar, setAvatar] = useState<File | null>(null);
  const [savingProfile, setSavingProfile] = useState(false);

  const [current, setCurrent] = useState("");
  const [password, setPassword] = useState("");
  const [confirm, setConfirm] = useState("");
  const [savingPw, setSavingPw] = useState(false);

  const saveProfile = async () => {
    setSavingProfile(true);
    try {
      const fd = new FormData();
      fd.append("_method", "PUT");
      fd.append("name", name);
      if (phone) fd.append("phone", phone);
      if (avatar) fd.append("avatar", avatar);
      await http.post("auth/me", fd);
      await refresh();
      setAvatar(null);
      toast.success("Profile updated.");
    } catch (e) {
      toast.error(e instanceof ApiError ? e.message : "Could not update profile.");
    } finally {
      setSavingProfile(false);
    }
  };

  const savePassword = async () => {
    if (password !== confirm) { toast.error("Passwords do not match."); return; }
    setSavingPw(true);
    try {
      await http.put("auth/me/password", { current_password: current, password, password_confirmation: confirm });
      setCurrent(""); setPassword(""); setConfirm("");
      toast.success("Password updated.");
    } catch (e) {
      toast.error(e instanceof ApiError ? e.message : "Could not update password.");
    } finally {
      setSavingPw(false);
    }
  };

  return (
    <div className="grid gap-6 lg:grid-cols-2">
      <Card className="rounded-2xl">
        <CardHeader><CardTitle className="text-base">Profile</CardTitle></CardHeader>
        <CardContent className="space-y-4">
          <div className="flex items-center gap-4">
            <PersonAvatar name={name} src={avatar ? URL.createObjectURL(avatar) : user?.avatar_url} className="h-16 w-16 text-lg" />
            <div>
              <Label className="cursor-pointer text-sm text-primary">
                Change photo
                <input type="file" accept="image/*" className="hidden" onChange={(e) => setAvatar(e.target.files?.[0] ?? null)} />
              </Label>
            </div>
          </div>
          <div className="space-y-1.5"><Label>Name</Label><Input value={name} onChange={(e) => setName(e.target.value)} /></div>
          <div className="space-y-1.5"><Label>Phone</Label><Input value={phone} onChange={(e) => setPhone(e.target.value)} /></div>
          <div className="space-y-1.5"><Label>Email</Label><Input value={user?.email ?? ""} disabled /></div>
          <Button onClick={saveProfile} disabled={savingProfile}>{savingProfile ? "Saving…" : "Save profile"}</Button>
        </CardContent>
      </Card>

      <Card className="rounded-2xl">
        <CardHeader><CardTitle className="text-base">Change password</CardTitle></CardHeader>
        <CardContent className="space-y-4">
          <div className="space-y-1.5"><Label>Current password</Label><Input type="password" value={current} onChange={(e) => setCurrent(e.target.value)} /></div>
          <div className="space-y-1.5"><Label>New password</Label><Input type="password" value={password} onChange={(e) => setPassword(e.target.value)} /></div>
          <div className="space-y-1.5"><Label>Confirm new password</Label><Input type="password" value={confirm} onChange={(e) => setConfirm(e.target.value)} /></div>
          <Button onClick={savePassword} disabled={savingPw || !current || !password}>{savingPw ? "Saving…" : "Update password"}</Button>
        </CardContent>
      </Card>
    </div>
  );
}
