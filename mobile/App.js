import React, { useRef, useState, useEffect } from 'react';
import {
  StyleSheet,
  SafeAreaView,
  View,
  Text,
  ActivityIndicator,
  TouchableOpacity,
  BackHandler,
  Platform,
  StatusBar
} from 'react-native';
import { WebView } from 'react-native-webview';

// Target URL requested: http://10.195.59.20:8000
const DEFAULT_URL = 'http://10.195.59.20:8000';
const ALT_URL = 'http://10.195.22.12:8000';

export default function App() {
  const webViewRef = useRef(null);
  const [currentUrl, setCurrentUrl] = useState(DEFAULT_URL);
  const [canGoBack, setCanGoBack] = useState(false);
  const [hasError, setHasError] = useState(false);
  const [errorMessage, setErrorMessage] = useState('');

  // Handle Android physical back button
  useEffect(() => {
    if (Platform.OS !== 'android') return;
    const onBackPress = () => {
      if (canGoBack && webViewRef.current) {
        webViewRef.current.goBack();
        return true;
      }
      return false;
    };
    const subscription = BackHandler.addEventListener('hardwareBackPress', onBackPress);
    return () => subscription.remove();
  }, [canGoBack]);

  const handleReload = () => {
    setHasError(false);
    setErrorMessage('');
    if (webViewRef.current) {
      webViewRef.current.reload();
    }
  };

  const handleSwitchUrl = (newUrl) => {
    setHasError(false);
    setErrorMessage('');
    setCurrentUrl(newUrl);
  };

  return (
    <SafeAreaView style={styles.safeArea}>
      <StatusBar barStyle="light-content" backgroundColor="#0f172a" />
      
      {hasError ? (
        <View style={styles.errorContainer}>
          <Text style={styles.errorIcon}>⚠️</Text>
          <Text style={styles.errorTitle}>Cannot Connect to Server</Text>
          <Text style={styles.errorSubtitle}>
            Target URL:{'\n'}
            <Text style={styles.urlText}>{currentUrl}</Text>
          </Text>
          <Text style={styles.errorHelp}>
            Please ensure:
            {'\n'}• Your phone and Mac are on the exact same Wi-Fi.
            {'\n'}• The PHP server is running on port 8000.
          </Text>
          {errorMessage ? (
            <Text style={styles.errorDetail}>{errorMessage}</Text>
          ) : null}
          <View style={styles.buttonRow}>
            <TouchableOpacity style={styles.retryButton} onPress={handleReload}>
              <Text style={styles.retryButtonText}>Retry</Text>
            </TouchableOpacity>
            <TouchableOpacity
              style={styles.switchButton}
              onPress={() => handleSwitchUrl(currentUrl === DEFAULT_URL ? ALT_URL : DEFAULT_URL)}
            >
              <Text style={styles.switchButtonText}>
                Switch to {currentUrl === DEFAULT_URL ? '10.195.22.12' : '10.195.59.20'}
              </Text>
            </TouchableOpacity>
          </View>
        </View>
      ) : (
        <WebView
          ref={webViewRef}
          source={{ uri: currentUrl }}
          style={styles.webview}
          javaScriptEnabled={true}
          domStorageEnabled={true}
          startInLoadingState={true}
          scalesPageToFit={true}
          allowsInlineMediaPlayback={true}
          originWhitelist={['*']}
          renderLoading={() => (
            <View style={styles.loadingContainer}>
              <ActivityIndicator size="large" color="#3b82f6" />
              <Text style={styles.loadingText}>Loading JodRod...</Text>
              <Text style={styles.loadingSubtext}>{currentUrl}</Text>
            </View>
          )}
          onNavigationStateChange={(navState) => {
            setCanGoBack(navState.canGoBack);
          }}
          onError={(syntheticEvent) => {
            const { nativeEvent } = syntheticEvent;
            setHasError(true);
            setErrorMessage(nativeEvent.description || 'Network connection failed');
          }}
          onHttpError={(syntheticEvent) => {
            const { nativeEvent } = syntheticEvent;
            if (nativeEvent.statusCode >= 400) {
              setHasError(true);
              setErrorMessage(`HTTP Error: ${nativeEvent.statusCode}`);
            }
          }}
        />
      )}
    </SafeAreaView>
  );
}

const styles = StyleSheet.create({
  safeArea: {
    flex: 1,
    backgroundColor: '#0f172a',
  },
  webview: {
    flex: 1,
    backgroundColor: '#ffffff',
  },
  loadingContainer: {
    ...StyleSheet.absoluteFillObject,
    justifyContent: 'center',
    alignItems: 'center',
    backgroundColor: '#0f172a',
  },
  loadingText: {
    marginTop: 14,
    color: '#e2e8f0',
    fontSize: 16,
    fontWeight: '600',
  },
  loadingSubtext: {
    marginTop: 6,
    color: '#64748b',
    fontSize: 12,
  },
  errorContainer: {
    flex: 1,
    justifyContent: 'center',
    alignItems: 'center',
    padding: 24,
    backgroundColor: '#0f172a',
  },
  errorIcon: {
    fontSize: 48,
    marginBottom: 16,
  },
  errorTitle: {
    color: '#f87171',
    fontSize: 20,
    fontWeight: 'bold',
    marginBottom: 8,
    textAlign: 'center',
  },
  errorSubtitle: {
    color: '#cbd5e1',
    fontSize: 14,
    textAlign: 'center',
    marginBottom: 16,
    lineHeight: 20,
  },
  urlText: {
    color: '#60a5fa',
    fontWeight: '600',
  },
  errorHelp: {
    color: '#94a3b8',
    fontSize: 13,
    lineHeight: 20,
    textAlign: 'left',
    alignSelf: 'stretch',
    backgroundColor: '#1e293b',
    padding: 16,
    borderRadius: 8,
    marginBottom: 20,
  },
  errorDetail: {
    color: '#fca5a5',
    fontSize: 12,
    marginBottom: 16,
    textAlign: 'center',
  },
  buttonRow: {
    flexDirection: 'row',
    gap: 12,
  },
  retryButton: {
    backgroundColor: '#2563eb',
    paddingVertical: 12,
    paddingHorizontal: 20,
    borderRadius: 8,
  },
  retryButtonText: {
    color: '#ffffff',
    fontSize: 15,
    fontWeight: '600',
  },
  switchButton: {
    backgroundColor: '#334155',
    paddingVertical: 12,
    paddingHorizontal: 16,
    borderRadius: 8,
  },
  switchButtonText: {
    color: '#e2e8f0',
    fontSize: 15,
    fontWeight: '600',
  },
});
