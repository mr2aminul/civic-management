// com/mr1aminul/civicmanagement/LeadBubbleService.java
package com.mr1aminul.civicmanagement;

import android.app.Notification;
import android.app.NotificationChannel;
import android.app.NotificationManager;
import android.app.Service;
import android.content.ClipData;
import android.content.ClipboardManager;
import android.content.Intent;
import android.graphics.PixelFormat;
import android.net.Uri;
import android.os.Build;
import android.os.IBinder;
import android.provider.Settings;
import android.util.DisplayMetrics;
import android.view.Gravity;
import android.view.LayoutInflater;
import android.view.MotionEvent;
import android.view.View;
import android.view.WindowManager;
import android.widget.FrameLayout;
import android.widget.ImageView;
import android.widget.LinearLayout;
import android.widget.TextView;
import android.widget.Toast;

import androidx.annotation.Nullable;
import androidx.core.app.NotificationCompat;

import com.google.android.material.bottomsheet.BottomSheetDialog;

import java.util.ArrayList;
import java.util.List;

public class LeadBubbleService extends Service {

    private static final String CHANNEL_ID = "floating_channel";

    private WindowManager wm;
    private LayoutInflater inflater;

    private View bubbleView;               // from bubble_lead.xml
    private View closeView;                // from remove_overlay.xml (trash bar)
    private View expandedView;             // from floating_lead_container.xml

    private WindowManager.LayoutParams bubbleLp;
    private WindowManager.LayoutParams closeLp;
    private WindowManager.LayoutParams expandedLp;

    private int screenW, screenH, statusBarH;
    private boolean isDragging = false;
    private boolean isExpanded = false;

    private final List<Lead> leads = new ArrayList<>();

    @Override
    public void onCreate() {
        super.onCreate();
        wm = (WindowManager) getSystemService(WINDOW_SERVICE);
        inflater = LayoutInflater.from(this);
        computeScreen();

        // sample data (replace with real feed)
        leads.add(new Lead("John Doe","01712345678","Project X","john@ex.com","Dhaka","3.5"));
        leads.add(new Lead("Amina","01822223333","Project Y","amina@ex.com","Gulshan","5.0"));

        createChannel();
        startForeground(1, buildNotification(getString(R.string.bubble_running)));

        addBubble();
        addCloseBar();
    }

    private void computeScreen() {
        DisplayMetrics dm = new DisplayMetrics();
        if (wm != null && wm.getDefaultDisplay() != null) {
            wm.getDefaultDisplay().getMetrics(dm);
        }
        screenW = dm.widthPixels;
        screenH = dm.heightPixels;
        // a small status bar guess; not critical
        statusBarH = (int)(24 * dm.density);
    }

    private void addBubble() {
        bubbleView = inflater.inflate(R.layout.bubble_lead, null);
        final TextView badge = bubbleView.findViewById(R.id.bubble_badge);
        final TextView iconText = bubbleView.findViewById(R.id.bubble_icon_text);
        iconText.setText("L");

        // badge
        int count = leads.size();
        if (count > 0) {
            badge.setText(String.valueOf(Math.min(99, count)));
            badge.setVisibility(View.VISIBLE);
        } else {
            badge.setVisibility(View.GONE);
        }

        bubbleLp = new WindowManager.LayoutParams(
                dp(56), dp(56),
                overlayType(),
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                        | WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
                PixelFormat.TRANSLUCENT
        );
        bubbleLp.gravity = Gravity.TOP | Gravity.START;
        bubbleLp.x = screenW - dp(56) - dp(16); // start right
        bubbleLp.y = screenH / 3;

        bubbleView.setOnClickListener(v -> toggleExpanded());

        bubbleView.setOnTouchListener(new View.OnTouchListener() {
            float downX, downY;
            int startX, startY;
            long downTs;

            @Override
            public boolean onTouch(View v, MotionEvent e) {
                switch (e.getAction()) {
                    case MotionEvent.ACTION_DOWN:
                        isDragging = false;
                        downX = e.getRawX();
                        downY = e.getRawY();
                        startX = bubbleLp.x;
                        startY = bubbleLp.y;
                        downTs = System.currentTimeMillis();
                        showTrash(true);
                        return true;

                    case MotionEvent.ACTION_MOVE:
                        int dx = (int) (e.getRawX() - downX);
                        int dy = (int) (e.getRawY() - downY);
                        if (Math.abs(dx) > dp(3) || Math.abs(dy) > dp(3)) {
                            isDragging = true;
                            bubbleLp.x = startX + dx;
                            bubbleLp.y = clampY(startY + dy);
                            wm.updateViewLayout(bubbleView, bubbleLp);
                            magnetizeToTrashIfNear();
                        }
                        return true;

                    case MotionEvent.ACTION_UP:
                        showTrash(false);
                        if (!isDragging && (System.currentTimeMillis() - downTs) < 250) {
                            // treat as click
                            v.performClick();
                        } else {
                            // if dropped over trash -> close
                            if (isOverTrash()) {
                                removeEverythingAndStop();
                            } else {
                                snapToEdge();
                            }
                        }
                        return true;
                }
                return false;
            }
        });

        ensureOverlayPermission();
        wm.addView(bubbleView, bubbleLp);
    }

    private void toggleExpanded() {
        if (isExpanded) {
            removeExpanded();
            isExpanded = false;
            return;
        }
        // show small panel near bubble
        expandedView = inflater.inflate(R.layout.floating_lead_container, null);
        LinearLayout container = expandedView.findViewById(R.id.leadContainer);

        // populate with fragment_lead_detail.xml
        for (Lead lead : leads) {
            View item = inflater.inflate(R.layout.fragment_lead_detail, container, false);
            TextView name = item.findViewById(R.id.leadName);
            TextView katha = item.findViewById(R.id.leadKatha);
            TextView phone = item.findViewById(R.id.leadPhone);
            TextView project = item.findViewById(R.id.leadProject);
            TextView email = item.findViewById(R.id.leadEmail);
            View openBtn = item.findViewById(R.id.btnOpenLead);

            if (name != null) name.setText(lead.name);
            if (katha != null) katha.setText("Katha: " + (lead.katha == null ? "-" : lead.katha));
            if (phone != null) phone.setText(lead.phone);
            if (project != null) project.setText(lead.project == null ? "" : lead.project);
            if (email != null) email.setText(lead.email == null ? "" : lead.email);

            // CTAs
            if (phone != null) {
                phone.setOnClickListener(v -> showPhoneOptions(lead.phone));
            }
            if (email != null && lead.email != null) {
                email.setOnClickListener(v -> {
                    Intent i = new Intent(Intent.ACTION_SENDTO);
                    i.setData(Uri.parse("mailto:" + lead.email));
                    i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                    startActivity(i);
                });
            }
            if (openBtn != null) {
                openBtn.setOnClickListener(v -> openLeadInApp(lead));
            }

            // clicking item opens details bottom sheet (fragment_lead_detail.xml)
            item.setOnClickListener(v -> showLeadDetail(lead));

            container.addView(item);
        }

        expandedLp = new WindowManager.LayoutParams(
                dp(300),
                WindowManager.LayoutParams.WRAP_CONTENT,
                overlayType(),
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                        | WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
                PixelFormat.TRANSLUCENT
        );

        // position: to the left of bubble if on right edge; otherwise to the right
        boolean onRight = bubbleLp.x > screenW / 2;
        expandedLp.gravity = Gravity.TOP | (onRight ? Gravity.END : Gravity.START);
        expandedLp.y = clampY(bubbleLp.y);
        expandedLp.x = onRight ? dp(16) : dp(16);

        wm.addView(expandedView, expandedLp);
        isExpanded = true;
    }

    private void removeExpanded() {
        if (expandedView != null) {
            try { wm.removeView(expandedView); } catch (Exception ignore) {}
            expandedView = null;
        }
    }

    private void showLeadDetail(Lead lead) {
        BottomSheetDialog dialog = new BottomSheetDialog(this);
        View v = inflater.inflate(R.layout.fragment_lead_detail, null);

        TextView tvName = v.findViewById(R.id.leadName);
        TextView tvPhone = v.findViewById(R.id.leadPhone);
        TextView tvEmail = v.findViewById(R.id.leadEmail);
        TextView tvAddress = v.findViewById(R.id.leadProject);

        if (tvName != null) tvName.setText(lead.name);
        if (tvPhone != null) tvPhone.setText(lead.phone);
        if (tvEmail != null) tvEmail.setText(lead.email == null ? "" : lead.email);
        if (tvAddress != null) tvAddress.setText(lead.address == null ? "" : lead.address);

        // Attach the CTA bottom sheet trigger to the phone TextView
        if (tvPhone != null) tvPhone.setOnClickListener(v2 -> showPhoneOptions(lead.phone));

        // Add quick CTA buttons area by inflating your bottom_sheet_phone_options as an inline footer (optional)
        // or let users tap phone to open the sheet.

        dialog.setContentView(v);
        dialog.show();
    }

    private void showPhoneOptions(String phone) {
        if (phone == null || phone.trim().isEmpty()) return;

        BottomSheetDialog dialog = new BottomSheetDialog(this);
        View sheet = inflater.inflate(R.layout.bottom_sheet_phone_options, null);

        // btnCall
        View btnCall = sheet.findViewById(R.id.btnCall);
        if (btnCall != null) btnCall.setOnClickListener(v -> {
            try {
                Intent i = new Intent(Intent.ACTION_DIAL, Uri.parse("tel:" + phone));
                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(i);
            } catch (Exception ignored) {}
            dialog.dismiss();
        });

        // btnWhatsapp
        View btnWa = sheet.findViewById(R.id.btnWhatsapp);
        if (btnWa != null) btnWa.setOnClickListener(v -> {
            try {
                String digits = phone.replaceAll("[^0-9]", "");
                Intent i = new Intent(Intent.ACTION_VIEW, Uri.parse("https://wa.me/" + digits));
                i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
                startActivity(i);
            } catch (Exception ignored) {}
            dialog.dismiss();
        });

        // btnCopy
        View btnCopy = sheet.findViewById(R.id.btnCopy);
        if (btnCopy != null) btnCopy.setOnClickListener(v -> {
            ClipboardManager cm = (ClipboardManager) getSystemService(CLIPBOARD_SERVICE);
            if (cm != null) {
                cm.setPrimaryClip(ClipData.newPlainText("phone", phone));
                Toast.makeText(this, "Copied: " + phone, Toast.LENGTH_SHORT).show();
            }
            dialog.dismiss();
        });

        dialog.setContentView(sheet);
        dialog.show();
    }

    private void openLeadInApp(Lead lead) {
        // Open in your in-app WebView (MainActivity)
        Intent i = new Intent(getApplicationContext(), MainActivity.class);
        i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
        i.putExtra("url", "https://civicgroupbd.com/lead/" + lead.phone);
        startActivity(i);
    }

    private void addCloseBar() {
        // IMPORTANT: make sure remove_overlay.xml has root with id @id/close_area_root and an ImageView @id/close_icon
        closeView = inflater.inflate(R.layout.remove_overlay, null);

        closeLp = new WindowManager.LayoutParams(
                WindowManager.LayoutParams.MATCH_PARENT,
                dp(100),
                overlayType(),
                WindowManager.LayoutParams.FLAG_NOT_FOCUSABLE
                        | WindowManager.LayoutParams.FLAG_LAYOUT_NO_LIMITS,
                PixelFormat.TRANSLUCENT
        );
        closeLp.gravity = Gravity.BOTTOM;
        closeView.setVisibility(View.GONE);
        wm.addView(closeView, closeLp);
    }

    private void showTrash(boolean show) {
        if (closeView != null) closeView.setVisibility(show ? View.VISIBLE : View.GONE);
    }

    private boolean isOverTrash() {
        if (closeView == null || bubbleView == null) return false;
        int[] trashLoc = new int[2];
        int[] bubbleLoc = new int[2];
        closeView.getLocationOnScreen(trashLoc);
        bubbleView.getLocationOnScreen(bubbleLoc);

        int trashTop = trashLoc[1];
        int bubbleCenterY = bubbleLoc[1] + bubbleView.getHeight() / 2;
        // consider “over trash” if bubble’s center is inside bottom 100dp
        return bubbleCenterY > (screenH - dp(120));
    }

    private void magnetizeToTrashIfNear() {
        // Simple visual effect: if near bottom, slightly pull bubble down
        if (bubbleLp.y > screenH - dp(180)) {
            bubbleLp.y += dp(6);
            if (bubbleLp.y > screenH - dp(120)) bubbleLp.y = screenH - dp(120);
            wm.updateViewLayout(bubbleView, bubbleLp);
        }
    }

    private void snapToEdge() {
        // Snap to left or right edge
        int mid = screenW / 2;
        bubbleLp.x = (bubbleLp.x + bubbleView.getWidth() / 2 < mid) ? dp(8) : (screenW - bubbleView.getWidth() - dp(8));
        bubbleLp.y = clampY(bubbleLp.y);
        wm.updateViewLayout(bubbleView, bubbleLp);
    }

    private int clampY(int y) {
        int min = statusBarH + dp(8);
        int max = screenH - bubbleView.getHeight() - dp(8) - dp(100); // keep above close bar
        if (y < min) y = min;
        if (y > max) y = max;
        return y;
    }

    private int overlayType() {
        return Build.VERSION.SDK_INT >= Build.VERSION_CODES.O
                ? WindowManager.LayoutParams.TYPE_APPLICATION_OVERLAY
                : WindowManager.LayoutParams.TYPE_PHONE;
    }

    private void ensureOverlayPermission() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.M && !Settings.canDrawOverlays(this)) {
            Intent i = new Intent(Settings.ACTION_MANAGE_OVERLAY_PERMISSION,
                    Uri.parse("package:" + getPackageName()));
            i.addFlags(Intent.FLAG_ACTIVITY_NEW_TASK);
            startActivity(i);
            Toast.makeText(this, getString(R.string.grant_overlay), Toast.LENGTH_LONG).show();
        }
    }

    private void removeEverythingAndStop() {
        removeExpanded();
        if (bubbleView != null) {
            try { wm.removeView(bubbleView); } catch (Exception ignore) {}
            bubbleView = null;
        }
        if (closeView != null) {
            try { wm.removeView(closeView); } catch (Exception ignore) {}
            closeView = null;
        }
        stopSelf();
    }

    private void createChannel() {
        if (Build.VERSION.SDK_INT >= Build.VERSION_CODES.O) {
            NotificationChannel ch = new NotificationChannel(
                    CHANNEL_ID,
                    getString(R.string.bubble_channel_name),
                    NotificationManager.IMPORTANCE_LOW
            );
            ch.setDescription(getString(R.string.bubble_channel_desc));
            NotificationManager nm = getSystemService(NotificationManager.class);
            if (nm != null) nm.createNotificationChannel(ch);
        }
    }

    private Notification buildNotification(String content) {
        NotificationCompat.Builder b = new NotificationCompat.Builder(this, CHANNEL_ID)
                .setSmallIcon(R.drawable.ic_lead) // make sure you have this
                .setContentTitle(getString(R.string.bubble_running))
                .setContentText(getString(R.string.open_leads))
                .setOngoing(true)
                .setPriority(NotificationCompat.PRIORITY_LOW);
        return b.build();
    }

    private int dp(int v) {
        return Math.round(getResources().getDisplayMetrics().density * v);
    }

    @Nullable
    @Override
    public IBinder onBind(Intent intent) { return null; }

    @Override
    public void onDestroy() {
        super.onDestroy();
        removeEverythingAndStop();
    }
}
