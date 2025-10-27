package com.mr1aminul.civicmanagement;

import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.net.Uri;
import android.view.LayoutInflater;
import android.view.View;
import android.view.ViewGroup;
import android.widget.Button;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.NonNull;
import androidx.recyclerview.widget.RecyclerView;

import java.util.List;

public class LeadAdapter extends RecyclerView.Adapter<LeadAdapter.LeadViewHolder> {

    private final List<LeadModel> leads;

    public LeadAdapter(List<LeadModel> leads) {
        this.leads = leads;
    }

    @NonNull
    @Override
    public LeadViewHolder onCreateViewHolder(@NonNull ViewGroup parent, int viewType) {
        View view = LayoutInflater.from(parent.getContext())
                .inflate(R.layout.fragment_lead_detail, parent, false);
        return new LeadViewHolder(view);
    }

    @Override
    public void onBindViewHolder(@NonNull LeadViewHolder holder, int position) {
        holder.bind(leads.get(position));
    }

    @Override
    public int getItemCount() {
        return leads.size();
    }

    public static class LeadViewHolder extends RecyclerView.ViewHolder {
        public LeadViewHolder(@NonNull View itemView) { super(itemView); }

        public void bind(LeadModel lead) {
            TextView nameTv = itemView.findViewById(R.id.leadName);
            TextView kathaTv = itemView.findViewById(R.id.leadKatha);
            TextView phoneTv = itemView.findViewById(R.id.leadPhone);
            TextView projectTv = itemView.findViewById(R.id.leadProject);
            TextView emailTv = itemView.findViewById(R.id.leadEmail);
            Button openBtn = itemView.findViewById(R.id.btnOpenLead);

            nameTv.setText(lead.name);
            projectTv.setText(lead.project);
            kathaTv.setText(lead.katha != null ? lead.katha : "");
            emailTv.setText(lead.email != null ? lead.email : "");

            // Phone CTA: call, WhatsApp, copy
            phoneTv.setOnClickListener(v -> {
                showPhoneOptions(itemView.getContext(), lead.phone);
            });

            // Open lead
            openBtn.setOnClickListener(v -> {
                if (lead.leadUrl != null && !lead.leadUrl.isEmpty()) {
                    Intent browserIntent = new Intent(Intent.ACTION_VIEW, Uri.parse(lead.leadUrl));
                    itemView.getContext().startActivity(browserIntent);
                }
            });
        }

        private void showPhoneOptions(Context context, String phone) {
            // Show simple options via chooser (Call / WhatsApp / Copy)
            CharSequence[] options = {"Call", "WhatsApp", "Copy"};
            androidx.appcompat.app.AlertDialog.Builder builder = new androidx.appcompat.app.AlertDialog.Builder(context);
            builder.setTitle("Choose action")
                    .setItems(options, (dialog, which) -> {
                        switch (which) {
                            case 0: // Call
                                context.startActivity(new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + phone)));
                                break;
                            case 1: // WhatsApp
                                String url = "https://wa.me/" + phone.replaceAll("\\D+", "");
                                try {
                                    context.startActivity(new Intent(Intent.ACTION_VIEW, Uri.parse(url)));
                                } catch (Exception e) {
                                    Toast.makeText(context, "Cannot open WhatsApp", Toast.LENGTH_SHORT).show();
                                }
                                break;
                            case 2: // Copy
                                ClipboardManager clipboard = (ClipboardManager) context.getSystemService(Context.CLIPBOARD_SERVICE);
                                ClipData clip = ClipData.newPlainText("phone", phone);
                                clipboard.setPrimaryClip(clip);
                                Toast.makeText(context, "Copied to clipboard", Toast.LENGTH_SHORT).show();
                                break;
                        }
                    }).show();
        }
    }
}
