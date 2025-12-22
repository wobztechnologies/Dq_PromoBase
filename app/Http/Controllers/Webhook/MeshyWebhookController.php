<?php

namespace App\Http\Controllers\Webhook;

use App\Jobs\ProcessMeshy3DGeneration;
use App\Models\ProductModel3D;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class MeshyWebhookController extends \App\Http\Controllers\Controller
{
    /**
     * Traiter les webhooks Meshy
     */
    public function handle(Request $request): Response
    {
        try {
            // Valider la signature du webhook si un secret est configuré
            $webhookSecret = Setting::get('meshy_webhook_secret');
            if ($webhookSecret) {
                // TODO: Implémenter la validation de signature si Meshy le supporte
                // Pour l'instant, on accepte tous les webhooks
            }

            $payload = $request->all();
            
            Log::info('Meshy Webhook - Réception', [
                'payload' => $payload,
            ]);

            // Vérifier la structure du payload
            // Format attendu de Meshy (à adapter selon la documentation réelle)
            $taskId = $payload['task_id'] ?? $payload['id'] ?? null;
            $status = $payload['status'] ?? null;
            $eventType = $payload['event'] ?? $payload['type'] ?? null;

            if (!$taskId) {
                Log::warning('Meshy Webhook - Task ID manquant', ['payload' => $payload]);
                return response('Task ID manquant', 400);
            }

            // Trouver le modèle 3D associé à cette tâche
            $model3D = ProductModel3D::where('meshy_task_id', $taskId)->first();

            if (!$model3D) {
                Log::warning('Meshy Webhook - Modèle 3D non trouvé pour la tâche', [
                    'task_id' => $taskId,
                ]);
                // Retourner 200 pour éviter que Meshy désactive le webhook
                return response('Modèle non trouvé', 200);
            }

            // Traiter selon le statut/événement
            // Meshy utilise: PENDING, IN_PROGRESS, SUCCEEDED, FAILED
            $statusUpper = strtoupper($status ?? '');
            
            if ($statusUpper === 'SUCCEEDED' || $eventType === 'task.completed') {
                // La tâche est terminée avec succès
                // Dispatcher le job pour télécharger et traiter le modèle
                Log::info('Meshy Webhook - Tâche terminée avec succès', [
                    'task_id' => $taskId,
                    'model_id' => $model3D->id,
                ]);

                // Régénérer le chemin selon la nouvelle structure : /ManufacturerName/SKU/assets/SKU-variant-numero.glb
                $bucket = config('filesystems.disks.s3.bucket');
                
                $product = $model3D->product;
                if ($product) {
                    // Récupérer la variante associée au modèle 3D (s'il y en a une)
                    $variantSku = null;
                    $colorVariant = $model3D->colorVariants()->first();
                    if ($colorVariant) {
                        $variantSku = $colorVariant->sku;
                    }
                    
                    // Générer le chemin selon la nouvelle structure
                    $s3Path = $product->generateAssetPath('glb', $variantSku);
                    $outputPath = 's3://' . $bucket . '/' . $s3Path;
                } else {
                    // Fallback si le produit n'existe plus
                    $outputPath = $model3D->s3_url 
                        ? ('s3://' . $bucket . '/' . $model3D->s3_url)
                        : ('s3://' . $bucket . '/models/ai-generated/' . $model3D->id . '.glb');
                }

                ProcessMeshy3DGeneration::dispatch($model3D->id, $taskId, $outputPath);
                
                return response('Webhook traité avec succès', 200);
            } 
            elseif ($statusUpper === 'FAILED' || $statusUpper === 'ERROR' || $eventType === 'task.failed') {
                // La tâche a échoué
                Log::error('Meshy Webhook - Tâche échouée', [
                    'task_id' => $taskId,
                    'model_id' => $model3D->id,
                    'error' => $payload['task_error'] ?? $payload['error'] ?? $payload['message'] ?? 'Erreur inconnue',
                ]);

                $model3D->update([
                    'status' => ProductModel3D::STATUS_ERROR,
                ]);

                return response('Erreur enregistrée', 200);
            }
            elseif ($statusUpper === 'PENDING' || $statusUpper === 'IN_PROGRESS') {
                // La tâche est toujours en cours
                Log::info('Meshy Webhook - Tâche en cours', [
                    'task_id' => $taskId,
                    'status' => $statusUpper,
                    'progress' => $payload['progress'] ?? null,
                ]);

                // Optionnel: mettre à jour un champ de progression si nécessaire
                return response('Statut enregistré', 200);
            }
            else {
                // Statut inconnu, logger pour debug
                Log::warning('Meshy Webhook - Statut inconnu', [
                    'task_id' => $taskId,
                    'status' => $status,
                    'event_type' => $eventType,
                    'payload' => $payload,
                ]);

                return response('Statut inconnu', 200);
            }
        } catch (\Exception $e) {
            Log::error('Meshy Webhook - Erreur lors du traitement', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'payload' => $request->all(),
            ]);

            // Retourner 200 pour éviter que Meshy désactive le webhook
            // mais logger l'erreur pour investigation
            return response('Erreur interne', 200);
        }
    }
}
