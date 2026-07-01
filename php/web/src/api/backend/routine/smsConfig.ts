import createAxios from '/@/utils/axios'

const url = '/admin/routine.SmsConfig/'

export const actionUrl = new Map([
    ['testSend', url + 'testSend'],
    ['encryptApiKey', url + 'encryptApiKey'],
])

export function testSend(data: {
    test_mobile: string
    sms_api_url: string
    sms_api_key: string
    sms_sign_id: string
    sms_template_id: string
}) {
    return createAxios(
        {
            url: actionUrl.get('testSend'),
            method: 'POST',
            data: data,
        },
        {
            showSuccessMessage: true,
        }
    )
}

export function encryptApiKey(apiKey: string) {
    return createAxios({
        url: actionUrl.get('encryptApiKey'),
        method: 'POST',
        data: { api_key: apiKey },
    })
}
