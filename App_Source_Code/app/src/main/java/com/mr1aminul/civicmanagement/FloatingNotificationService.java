//FloatingNotificationService.java
package com.mr1aminul.civicmanagement;

import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.PendingIntent;
import android.app.Service;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Context;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.net.Uri;
import android.os.Build;
import android.os.Handler;
import android.os.IBinder;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.MotionEvent;
import android.view.View;
import android.view.WindowManager;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

import com.google.android.material.bottomsheet.BottomSheetDialog;

import java.util.ArrayList;

public class FloatingNotificationService extends Service {

    private WindowManager windowManager;
    private LinearLayout bubbleContainer;
    private ArrayList<Lead> leadList = new ArrayList<>();
    private int bubbleSize;

    @Override
    public void onCreate() {
        super.onCreate();
        windowManager = (WindowManager) getSystemService(WINDOW_SERVICE);
        bubbleSize = getResources().getDimensionPixelSize(R.dimen.bubble_size);

        createNotificationChannel();
        startForeground(1, getNotification("Floating leads active"));

        showInitialBubble();
    }

    private void showInitialBubble() {
        bubbleContainer = new LinearLayout(this);
        bubbleContainer.setOrientation(LinearLayout.VERTICAL);

        // Example lead
        Lead exampleLead = new Lead(
                "John Doe",
                "01712345678",
                "Project X",
                "johndoe@gmail.com",
                "Dhaka, Bangladesh",
                "Facebook Ads"
        );

        leadList.add(exampleLead);

        for (Lead lead : leadList) {
            addLeadBubble(lead);
        }
    }

    private void addLeadBubble(Lead lead) {
        final View bubble = LayoutInflater.from(this).inflate(R.layout.chat_head_layout, null);

        bubble.setOnTouchListener(new FloatingTouchListener(bubble));
        bubble.setOnClickListener(v -> showLeadDetail(lead));

        WindowManager.LayoutParams params = new WindowManager.LayoutParams(
                bubbleSize,
                bubbleSize,
                Build.VERSION.SDK_INT >= Build.VERSION_CODES.O ?
                        WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY :
                        WindowManager.LayoutParams.TYPE_PHONE,
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE,
                PixelFormat.TRANSLUCENT
        );
        params.gravity = Gravity.TOP | Gravity.START;
        params.x = 20;
        params.y = 200;

        windowManager.addView(bubble, params);
    }

    private void showLeadDetail(Lead lead) {
        BottomSheetDialog dialog = new BottomSheetDialog(this);
        View view = LayoutInflater.from(this).inflate(R.layout.fragment_lead_detail, null);

        ((TextView) view.findViewById(R.id.leadName)).setText(lead.name);
        ((TextView) view.findViewById(R.id.leadProject)).setText(lead.project);

        TextView phoneTv = view.findViewById(R.id.leadPhone);
        phoneTv.setText(lead.phone);
        phoneTv.setOnClickListener(v -> showPhoneOptions(lead.phone));

        if (lead.email != null) {
            TextView emailTv = view.findViewById(R.id.leadEmail);
            emailTv.setText(lead.email);
            emailTv.setOnClickListener(v -> {
                Intent intent = new Intent(Intent.ACTION_SENDTO);
                intent.setData(Uri.parse("mailto:" + lead.email));
                intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(intent);
            });
        }

        view.findViewById(R.id.btnOpenLead).setOnClickListener(v -> {
            Intent intent = new Intent(this, MainActivity.class);
            intent.putExtra("url", "https://civicgroupbd.com/lead/" + lead.phone);
            intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(intent);
        });

        dialog.setContentView(view);
        dialog.show();
    }

    private void showPhoneOptions(String phone) {
        if (phone == null || phone.isEmpty()) return;

        BottomSheetDialog dialog = new BottomSheetDialog(this);
        View sheetView = LayoutInflater.from(this).inflate(R.layout.bottom_sheet_phone_options, null);
        dialog.setContentView(sheetView);

        sheetView.findViewById(R.id.btnCall).setOnClickListener(v -> {
            try {
                Intent intent = new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + phone));
                intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(intent);
            } catch (Exception e) {
                e.printStackTrace();
            }
            dialog.dismiss();
        });

        sheetView.findViewById(R.id.btnWhatsapp).setOnClickListener(v -> {
            try {
                String url = "https://wa.me/" + phone.replaceAll("[^0-9]", "");
                Intent intent = new Intent(Intent.ACTION_VIEW, Uri.parse(url));
                intent.setFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(intent);
            } catch (Exception e) {
                e.printStackTrace();
            }
            dialog.dismiss();
        });

        sheetView.findViewById(R.id.btnCopy).setOnClickListener(v -> {
            ClipboardManager clipboard = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
            ClipData clip = ClipData.newPlainText("phone", phone);
            clipboard.setPrimaryClip(clip);
            Toast.makeText(this, "Phone copied: " + phone, Toast.LENGTH_SHORT).show();
            dialog.dismiss();
        });

        dialog.show();
    }

    private class FloatingTouchListener implements View.OnTouchListener {
        private final View view;
        private int lastX, lastY;
        private float initialTouchX, initialTouchY;

        FloatingTouchListener(View view) { this.view = view; }

        @Override
        public boolean onTouch(View v, MotionEvent event) {
            WindowManager.LayoutParams params = (WindowManager.LayoutParams) view.getLayoutParams();
            switch (event.getAction()) {
                case MotionEvent.ACTION_DOWN:
                    lastX = params.x;
                    lastY = params.y;
                    initialTouchX = event.getRawX();
                    initialTouchY = event.getRawY();
                    return true;
                case MotionEvent.ACTION_MOVE:
                    params.x = lastX + (int) (event.getRawX() - initialTouchX);
                    params.y = lastY + (int) (event.getRawY() - initialTouchY);
                    windowManager.updateViewLayout(view, params);
                    return true;
            }
            return false;
        }
    }

    private NotificationCompat.Builder getNotificationBuilder(String text) {
        return new NotificationCompat.Builder(this, "floating_channel")
                .setContentTitle("Civic Leads")
                .setContentText(text)
                .setSmallIcon(R.drawable.ic_lead)
                .setPriority(NotificationCompat.PRIORITY_LOW)
                .setCategory(NotificationCompat.CATEGORY_SERVICE)
                .setOngoing(true);
    }

    private android.app.Notification getNotification(String text) {
        return getNotificationBuilder(text).build();
    }

    private void createNotificationChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel channel = new NotificationChannel(
                    "floating_channel",
                    "Floating Leads",
                    NotificationManager.IMPORTANCE_LOW
            );
            NotificationManager manager = getSystemService(NotificationManager.class);
            if (manager != null) manager.createNotificationChannel(channel);
        }
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) {
        return null;
    }
}
