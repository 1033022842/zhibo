// 只打包实际用到的图标（构建脚本生成，勿手改）
import { addCollection } from '@iconify/vue'
import usedIcons from '@/assets/used-icons.json'
addCollection(usedIcons as any)
