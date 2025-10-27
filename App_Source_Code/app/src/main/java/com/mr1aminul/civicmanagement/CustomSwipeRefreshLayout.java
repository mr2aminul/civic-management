package com.mr1aminul.civicmanagement;

import android.content.Context;
import android.util.AttributeSet;
import android.view.MotionEvent;
import android.view.ViewConfiguration;
import android.webkit.WebView;
import androidx.swiperefreshlayout.widget.SwipeRefreshLayout;

public class CustomSwipeRefreshLayout extends SwipeRefreshLayout {
    private WebView mWebView;
    private int mTouchSlop;
    private float mInitialDownY;
    private boolean mIsBeingDragged;
    private static final int SCROLL_THRESHOLD = 10; // Minimum scroll position to disable refresh

    public CustomSwipeRefreshLayout(Context context) {
        this(context, null);
    }

    public CustomSwipeRefreshLayout(Context context, AttributeSet attrs) {
        super(context, attrs);
        mTouchSlop = ViewConfiguration.get(context).getScaledTouchSlop();
    }

    @Override
    protected void onFinishInflate() {
        super.onFinishInflate();
        // Find the WebView child
        findWebView(this);
    }

    private void findWebView(android.view.ViewGroup parent) {
        for (int i = 0; i < parent.getChildCount(); i++) {
            android.view.View child = parent.getChildAt(i);
            if (child instanceof WebView) {
                mWebView = (WebView) child;
                return;
            } else if (child instanceof android.view.ViewGroup) {
                findWebView((android.view.ViewGroup) child);
            }
        }
    }

    @Override
    public boolean onInterceptTouchEvent(MotionEvent ev) {
        switch (ev.getActionMasked()) {
            case MotionEvent.ACTION_DOWN:
                mInitialDownY = ev.getY();
                mIsBeingDragged = false;
                break;

            case MotionEvent.ACTION_MOVE:
                if (mIsBeingDragged) {
                    return super.onInterceptTouchEvent(ev);
                }

                // Check if WebView is scrolled down more than threshold
                if (mWebView != null && mWebView.getScrollY() > SCROLL_THRESHOLD) {
                    return false; // Don't intercept if scrolled down
                }

                final float y = ev.getY();
                final float yDiff = y - mInitialDownY;

                // Only allow refresh if pulling down from the very top
                if (yDiff > mTouchSlop && canChildScrollUp() == false) {
                    mIsBeingDragged = true;
                    return super.onInterceptTouchEvent(ev);
                }
                break;

            case MotionEvent.ACTION_UP:
            case MotionEvent.ACTION_CANCEL:
                mIsBeingDragged = false;
                break;
        }

        return super.onInterceptTouchEvent(ev);
    }

    @Override
    public boolean canChildScrollUp() {
        if (mWebView != null) {
            return mWebView.getScrollY() > 0;
        }
        return super.canChildScrollUp();
    }

    @Override
    public boolean onTouchEvent(MotionEvent ev) {
        // Additional check during touch event
        if (mWebView != null && mWebView.getScrollY() > SCROLL_THRESHOLD) {
            return false; // Don't handle touch if scrolled down
        }
        return super.onTouchEvent(ev);
    }
}
