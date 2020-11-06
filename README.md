# crypto-portfolio
暗号通貨 幻想計算機  

幻想計算機は、 [赤字計算機](https://github.com/arahaya/crypto-portfolio) を独断と偏見で改良したものです。

# セットアップ
このアプリケーションは Firebase を使っています。
利用前に、 `firebase-config.php` を設定してください。

Cloud Firestore を利用するため、 Firebase のダッシュボードでデータベースを作成する必要があります。
幻想計算機は `portfolios/{userId}` という名前で文書を作成するので、セキュリティルールをそれに合わせて設定します。

例)

```
service cloud.firestore {
  match /databases/{database}/documents {
    match /portfolios/{userId} {
      allow read, write: if userId == request.auth.uid;
    }
  }
}
```

# デプロイ

Could Run にデプロイする手順は以下の通り

```bash
$ gcloud builds submit --tag="asia.gcr.io/${PROJECT_ID}/crypto-portfolio" --project="${PROJECT_ID}"
$ gcloud run deploy --image="asia.gcr.io/${PROJECT_ID}/crypto-portfolio" --port=80 --cpu=1 --memory=128Mi --platform=managed --region=asia-northeast1 --project="${PROJECT_ID}"
```
